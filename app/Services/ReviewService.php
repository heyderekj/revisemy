<?php

namespace App\Services;

use App\Events\ReviewDecided;
use App\Events\ReviewHandedOff;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Review;
use App\Models\Screenshot;
use App\Models\Workspace;
use App\Services\Capture\HostedCaptureDriver;
use App\Services\Capture\PageCaptureService;
use App\Support\ElementMap;
use App\Support\OutboundUrl;
use App\Support\ToolProgress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReviewService
{
    public function __construct(
        protected ScreenshotStorage $screenshots,
        protected SecondOpinionService $opinions,
        protected PageCaptureService $capture,
        protected DocumentIngestionService $documents,
        protected CreditsService $credits,
    ) {}

    /**
     * Create a review from a validated request payload, resolving whichever
     * source was provided: uploaded images, a server-side page capture
     * (capture_url + page_url), a PDF (presentation), or raw HTML (email).
     * Exactly one source is required; type is inferred from the source when
     * not explicit. Debits workspace credits before capture; refunds if
     * ingest fails after the debit.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InsufficientCreditsException
     */
    public function createFromRequest(Workspace $workspace, array $data, ?string $origin = null): Review
    {
        $images = $data['images'] ?? [];
        $sources = array_filter([
            'images' => $images !== [] && $images !== null,
            'capture_url' => (bool) ($data['capture_url'] ?? false),
            'pdf' => isset($data['pdf']) && $data['pdf'] !== '',
            'html' => isset($data['html']) && $data['html'] !== '',
        ]);

        if (count($sources) !== 1) {
            throw ValidationException::withMessages([
                'images' => 'Provide exactly one source: images, capture_url (with page_url), pdf, or html.',
            ]);
        }

        $pageUrl = trim((string) ($data['page_url'] ?? ''));

        // A follow-up pass inherits page_url in create(), but capture_url needs
        // it now — so "re-capture the same page" works without re-passing it.
        if ($pageUrl === '' && ! empty($data['parent_id'])) {
            $pageUrl = (string) $this->findForWorkspace($workspace, (string) $data['parent_id'])?->page_url;
        }

        if (isset($sources['capture_url']) && ($pageUrl === '' || ! filter_var($pageUrl, FILTER_VALIDATE_URL))) {
            throw ValidationException::withMessages([
                'page_url' => 'capture_url needs a valid page_url to render.',
            ]);
        }

        // Each uploaded image may come with its DPR and the element map the
        // agent collected on the page it shot (image_details, same order).
        if (isset($sources['images'])) {
            $details = array_values((array) ($data['image_details'] ?? []));
            $images = array_map(
                fn ($image, int $index) => is_array($details[$index] ?? null)
                    ? ['upload' => $image, 'dpr' => $details[$index]['dpr'] ?? null, 'elements' => self::elementsFrom($details[$index]['elements'] ?? null)]
                    : $image,
                array_values($images),
                array_keys(array_values($images)),
            );
        }

        $cost = $this->credits->costForSources($sources);
        $debited = $this->credits->debit($workspace, $cost);

        // A capture can run past PHP's time limit, and a fatal error skips
        // every catch below. Give it the time its own budget allows, and if
        // the request still dies before it's settled, hand the credits back.
        $settled = false;
        register_shutdown_function(function () use (&$settled, $workspace, $debited): void {
            if (! $settled) {
                $this->credits->refund($workspace, $debited);
            }
        });

        if (! isset($sources['images'])) {
            $this->allowTimeFor($sources);
        }

        $type = $data['type'] ?? null;
        $domHtml = null;
        $captureMs = null;

        try {
            if (isset($sources['capture_url'])) {
                ToolProgress::report('Opening '.(parse_url($pageUrl, PHP_URL_HOST) ?: $pageUrl).' to capture it', 'open');
                $started = microtime(true);
                ['shots' => $images, 'dom' => $domHtml] = $this->capture->capturePage($pageUrl);
                $captureMs = (int) round((microtime(true) - $started) * 1000);
                $type ??= Review::TYPE_WEBSITE;
            } elseif (isset($sources['pdf'])) {
                ToolProgress::report('Rendering the slides', 'render');
                $images = $this->documents->pdfToImages((string) $data['pdf']);
                $type ??= Review::TYPE_PRESENTATION;
            } elseif (isset($sources['html'])) {
                ToolProgress::report('Rendering the email', 'render');
                $images = $this->capture->captureHtml((string) $data['html']);
                $domHtml = (string) $data['html'];
                $type ??= Review::TYPE_EMAIL;
            }

            ToolProgress::report('Saving the review and its second opinion', 'save');
            $started = microtime(true);

            $review = $this->create(
                $workspace,
                $data['title'],
                $data['context'] ?? null,
                $images,
                $pageUrl !== '' ? $pageUrl : null,
                $data['parent_id'] ?? null,
                $type,
                $domHtml,
                $data['webhook_url'] ?? null,
                $origin,
            );
            $settled = true;

            if ($captureMs !== null) {
                $this->logCaptureTimings($review, $images, $captureMs, (int) round((microtime(true) - $started) * 1000));
            }

            return $review;
        } catch (ValidationException $e) {
            $this->credits->refund($workspace, $debited);
            $settled = true;

            if (! isset($sources['capture_url']) || ! $this->captureCanFallOpen($e)) {
                throw $e;
            }

            $reason = collect($e->errors())->flatten()->first() ?? 'Capture failed.';

            return $this->create(
                $workspace,
                $data['title'],
                trim("Capture failed, so this board has no page shot yet. Add a screenshot, or open the page from the link. {$reason}\n\n".($data['context'] ?? '')),
                [[
                    'binary' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
                    'meta' => ['origin' => 'capture_failed', 'page_url' => $pageUrl],
                ]],
                $pageUrl !== '' ? $pageUrl : null,
                $data['parent_id'] ?? null,
                $type ?? Review::TYPE_WEBSITE,
                null,
                $data['webhook_url'] ?? null,
                $origin,
            );
        } catch (Throwable $e) {
            $this->credits->refund($workspace, $debited);
            $settled = true;

            throw $e;
        }
    }

    /**
     * Raise PHP's time limit to what this source can take: every viewport's
     * capture session one after another, plus the DOM load and saving. The
     * sessions usually run side by side, but a plan that turns the extra
     * browsers away sends them back to one at a time. Only ever raised,
     * never lowered, and left alone where there is no limit.
     *
     * @param  array<string, bool>  $sources
     */
    protected function allowTimeFor(array $sources): void
    {
        $limit = (int) ini_get('max_execution_time');

        if ($limit <= 0 || ! function_exists('set_time_limit')) {
            return;
        }

        $perLoad = app(HostedCaptureDriver::class)->functionBudget(sweep: true, collect: true)['httpTimeout'];
        $loads = isset($sources['capture_url']) ? count($this->capture->viewports()) + 1 : 1;
        $needed = $perLoad * $loads + 60;

        if ($needed > $limit) {
            @set_time_limit($needed);
        }
    }

    /**
     * One line per capture, so the waits inside a shot can be tuned from what
     * real pages take: each viewport's session and phases, the whole capture
     * and the time spent storing it.
     *
     * @param  list<array{meta?: array<string, mixed>|null}|mixed>  $shots
     */
    protected function logCaptureTimings(Review $review, array $shots, int $captureMs, int $storeMs): void
    {
        $viewports = [];

        foreach ($shots as $shot) {
            $meta = is_array($shot) ? ($shot['meta'] ?? []) : [];

            if (isset($meta['viewport'])) {
                $viewports[$meta['viewport']] = array_filter([
                    'capture_ms' => $meta['capture_ms'] ?? null,
                    'timings' => $meta['timings'] ?? null,
                ], fn ($value) => $value !== null);
            }
        }

        Log::info('Capture timings', [
            'review' => $review->public_id,
            'page_url' => $review->page_url,
            'capture_ms' => $captureMs,
            'store_ms' => $storeMs,
            'viewports' => $viewports,
        ]);
    }

    protected function captureCanFallOpen(ValidationException $e): bool
    {
        $message = (string) (collect($e->errors())->flatten()->first() ?? '');

        return str_contains($message, 'capture_provider_failed')
            || str_contains($message, 'capture_not_configured');
    }

    /**
     * @param  list<string|UploadedFile|array{binary: string, meta: array<string, mixed>|null, elements?: array<string, mixed>|null, thumb?: string|null, vision?: array{binary: string, crop: float}|null, slices?: list<array{binary: string, top: float, height: float}>}>  $images
     */
    public function create(
        Workspace $workspace,
        string $title,
        ?string $context,
        array $images,
        ?string $pageUrl = null,
        ?string $parentPublicId = null,
        ?string $type = null,
        ?string $domHtml = null,
        ?string $webhookUrl = null,
        ?string $origin = null,
    ): Review {
        if ($type !== null && ! in_array($type, Review::types(), true)) {
            throw ValidationException::withMessages([
                'type' => 'Type must be one of: '.implode(', ', Review::types()).'.',
            ]);
        }

        if ($webhookUrl !== null) {
            $this->assertValidWebhookUrl($webhookUrl);
        }

        if ($images === []) {
            throw ValidationException::withMessages([
                'images' => 'Add at least one screenshot so there is something to look at.',
            ]);
        }

        if (count($images) > 5) {
            throw ValidationException::withMessages([
                'images' => 'Keep it to 5 screenshots per review for now.',
            ]);
        }

        // Resolve every source up front: this validates before a review row
        // exists, and the bytes are kept so store() below never re-downloads
        // an https source or re-decodes a base64 one.
        $resolved = [];

        foreach (array_values($images) as $index => $image) {
            if (($upload = self::uploadOf($image)) !== null) {
                $resolved[$index] = $this->screenshots->validateSource($upload);
            }
        }

        $parent = null;
        $pass = 1;

        if ($parentPublicId) {
            $parent = $this->findForWorkspace($workspace, $parentPublicId);

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'No parent review with that id for this try token.',
                ]);
            }

            $pass = ((int) $parent->pass) + 1;
            $context ??= $parent->context;
            $pageUrl ??= $parent->page_url;
            $type ??= $parent->type;
            // A pipeline sets the webhook once; follow-up passes inherit it,
            // and its health with it — a paused webhook stays paused until a
            // pass names a webhook_url again.
            if ($webhookUrl === null && $parent->webhook_url) {
                $webhookUrl = $parent->webhook_url;
                $webhookHealth = [
                    'webhook_failures' => (int) $parent->webhook_failures,
                    'webhook_paused_at' => $parent->webhook_paused_at,
                    'webhook_last_error' => $parent->webhook_last_error,
                ];
            }
        }

        $retentionDays = $workspace->reviewRetentionDays();

        $review = $workspace->reviews()->create([
            'parent_id' => $parent?->id,
            'title' => $title,
            'context' => $context,
            'type' => $type ?? Review::TYPE_UI,
            'origin' => $origin,
            'page_url' => $pageUrl,
            'webhook_url' => $webhookUrl,
            ...($webhookHealth ?? []),
            'pass' => $pass,
            'expires_at' => now()->addDays($retentionDays),
        ]);

        if ($domHtml !== null && trim($domHtml) !== '') {
            try {
                $path = 'reviews/'.$review->public_id.'/dom.html';
                Storage::disk(ScreenshotStorage::diskName())->put($path, substr($domHtml, 0, 2 * 1024 * 1024));
                $review->update(['dom_path' => $path]);
            } catch (Throwable $e) {
                Log::warning('DOM snapshot persist failed', ['review' => $review->public_id, 'error' => $e->getMessage()]);
            }
        }

        $shots = [];
        foreach (array_values($images) as $index => $image) {
            $upload = self::uploadOf($image);
            $shot = $upload === null
                ? $this->screenshots->storeRaw($review, $image['binary'], $index, $image['meta'] ?? null, $image['thumb'] ?? null)
                : $this->screenshots->store($review, $upload, $index, binary: $resolved[$index]);

            if ($upload !== null && is_array($image)) {
                $this->applyUploadDetails($shot, $image['dpr'] ?? null, $image['elements'] ?? null);
            } elseif (is_array($image) && is_array($image['elements'] ?? null)) {
                $this->screenshots->storeElements($shot, $image['elements']);
            }
            if (is_array($image) && is_string($image['vision']['binary'] ?? null)) {
                $this->screenshots->storeVision($shot, $image['vision']['binary'], (float) ($image['vision']['crop'] ?? 1));
            }
            if (is_array($image) && ! empty($image['slices'])) {
                $this->screenshots->storeSlices($shot, $image['slices']);
            }
            $shots[] = $shot;
        }

        // Every shot is stored first, so the review's one checklist sees all viewports.
        foreach ($shots as $shot) {
            $this->opinions->queue($shot);
        }

        return $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent']) ?? $review;
    }

    /**
     * @param  array<string, mixed>|null  $elements
     */
    public function addScreenshot(Review $review, string|UploadedFile $image, ?float $dpr = null, ?array $elements = null): Screenshot
    {
        if (! $review->isOpenForFeedback()) {
            throw ValidationException::withMessages([
                'review' => 'This review is closed — start a fresh pass with create_review (and parent_id if this is a follow-up).',
            ]);
        }

        if ($review->screenshots()->count() >= 5) {
            throw ValidationException::withMessages([
                'images' => 'This review already has 5 screenshots.',
            ]);
        }

        $sortOrder = (int) $review->screenshots()->max('sort_order') + 1;

        $shot = $this->screenshots->store($review, $image, $sortOrder);
        $this->applyUploadDetails($shot, $dpr, $elements);
        $this->opinions->queue($shot);

        return $shot;
    }

    /**
     * An uploaded image's DPR and element map. The DPR says what its pixels
     * hold per CSS px, so the review shows it at the size it was drawn at;
     * without one, a map's page width works it out. A map that doesn't line
     * up with the image is left out, and meta.elements_skipped says why, so
     * the agent can see it in the payload. The image is always kept.
     *
     * @param  array<string, mixed>|null  $elements
     */
    protected function applyUploadDetails(Screenshot $shot, mixed $dpr, ?array $elements): void
    {
        $dpr = is_numeric($dpr) && $dpr >= 0.5 && $dpr <= 4 ? round((float) $dpr, 2) : null;
        $docWidth = is_array($elements) && is_numeric($elements['docWidth'] ?? null) ? (float) $elements['docWidth'] : null;
        $width = (int) $shot->width;

        if ($dpr === null && $docWidth > 0 && $width > 0) {
            $guess = $width / $docWidth;
            $dpr = $guess >= 0.5 && $guess <= 4 ? round($guess, 2) : null;
        }

        if ($dpr !== null) {
            $shot->update(['meta' => [...($shot->meta ?? []), 'dpr' => $dpr]]);
        }

        if (! is_array($elements)) {
            return;
        }

        // The map's page should be as wide as the image at its DPR.
        if ($docWidth === null || $dpr === null || abs($docWidth * $dpr - $width) > $width * 0.02) {
            $shot->update(['meta' => [...($shot->meta ?? []), 'elements_skipped' => $docWidth === null
                ? 'the element map has no docWidth'
                : "the element map is {$docWidth} CSS px wide, which doesn't match a {$width}px image".($dpr !== null ? " at dpr {$dpr}" : ''),
            ]]);

            return;
        }

        $this->screenshots->storeElements($shot, $elements, 'upload');
    }

    /**
     * The uploaded source in an images entry: the entry itself, or the
     * upload in an entry that carries details. Null for a server capture.
     */
    protected static function uploadOf(mixed $image): string|UploadedFile|null
    {
        if (is_string($image) || $image instanceof UploadedFile) {
            return $image;
        }

        return is_array($image) && (is_string($image['upload'] ?? null) || ($image['upload'] ?? null) instanceof UploadedFile)
            ? $image['upload']
            : null;
    }

    /**
     * An element map as sent: an object, or JSON text (multipart forms).
     *
     * @return array<string, mixed>|null
     */
    public static function elementsFrom(mixed $elements): ?array
    {
        if (is_string($elements) && strlen($elements) <= ElementMap::MAX_BYTES) {
            $elements = json_decode($elements, true);
        }

        return is_array($elements) ? $elements : null;
    }

    /**
     * Human decision on a pass: approve or request changes. Approving accepts
     * the agent's resolutions on this pass and the parent it built on. Shared
     * by the review page and the MCP app's decide_review tool.
     */
    public function decide(Review $review, string $status, ?string $note = null): bool
    {
        if (! in_array($status, [Review::STATUS_APPROVED, Review::STATUS_CHANGES_REQUESTED], true)) {
            return false;
        }

        if (! $review->isOpenForFeedback()) {
            return false;
        }

        $review->update([
            'status' => $status,
            'decision_note' => $note !== null && $note !== '' ? $note : null,
            'decision_at' => now(),
        ]);

        if ($status === Review::STATUS_APPROVED) {
            $lifecycle = app(MarkLifecycleService::class);
            $lifecycle->verifyResolvedForReview($review);

            if ($review->parent) {
                $lifecycle->verifyResolvedForReview($review->parent);
            }
        }

        ReviewDecided::dispatch($review);

        return true;
    }

    /**
     * The person sets a review's status. While it waits on them this is the
     * first decision (decide()); after that they can reopen it or switch it
     * between approved and changes requested, on the latest pass only (see
     * Review::canChangeStatus). Marks keep their own statuses. Fires
     * ReviewDecided either way, so open pages, the webhook and a waiting
     * agent all hear it.
     */
    public function setStatus(Review $review, string $status, ?string $note = null): bool
    {
        if ($review->status === Review::STATUS_PENDING) {
            return $this->decide($review, $status, $note);
        }

        $statuses = [Review::STATUS_PENDING, Review::STATUS_APPROVED, Review::STATUS_CHANGES_REQUESTED];

        if (! in_array($status, $statuses, true) || $status === $review->status || ! $review->canChangeStatus()) {
            return false;
        }

        $reopening = $status === Review::STATUS_PENDING;

        $review->update([
            'status' => $status,
            'decision_note' => $reopening || $note === null || $note === '' ? null : $note,
            'decision_at' => $reopening ? null : now(),
        ]);

        if ($status === Review::STATUS_APPROVED) {
            $lifecycle = app(MarkLifecycleService::class);
            $lifecycle->verifyResolvedForReview($review);

            if ($review->parent) {
                $lifecycle->verifyResolvedForReview($review->parent);
            }
        }

        ReviewDecided::dispatch($review);

        return true;
    }

    /** Puts every pass of the review's chain away, or brings them back. */
    public function archive(Review $review, bool $archived = true): void
    {
        Review::query()
            ->whereIn('id', $review->chain()->pluck('id'))
            ->update(['archived_at' => $archived ? now() : null]);

        $review->refresh();
    }

    /**
     * Deletes every pass of the review's chain with its files, newest pass
     * first so no parent is removed under a child. The same clean-up the
     * nightly prune runs.
     */
    public function deleteChain(Review $review): void
    {
        $review->chain()
            ->sortByDesc('pass')
            ->each(fn (Review $pass) => $pass->prune());
    }

    public function findForWorkspace(Workspace $workspace, string $publicId): ?Review
    {
        return $workspace->reviews()
            ->where('public_id', $publicId)
            ->with(['screenshots.annotations', 'screenshots.findings', 'parent'])
            ->first();
    }

    /**
     * A review by id, or by the review link someone pasted. A link to a review
     * started on the site, that no agent has yet, moves into this workspace:
     * holding the owner link is already the reviewer's whole login. Guest
     * links never count, and a review another agent holds stays where it is.
     * $agent names who took it ("Claude"), for the review page to say so.
     */
    public function findOrAdopt(Workspace $workspace, string $idOrLink, ?string $agent = null): ?Review
    {
        $idOrLink = trim($idOrLink);

        if ($review = $this->findForWorkspace($workspace, $idOrLink)) {
            return $this->takenBy($review, $agent);
        }

        $token = $this->ownerTokenFrom($idOrLink);

        if ($token === null) {
            return null;
        }

        $review = Review::query()->where('token', $token)->first();

        if (! $review || ! hash_equals((string) $review->token, $token)) {
            return null;
        }

        // An agent connected from the browser that made the review already
        // shares its workspace. Opening the link still hands it over.
        if ($review->workspace_id === $workspace->id) {
            $own = $this->findForWorkspace($workspace, $review->public_id);

            return $own ? $this->takenBy($own, $agent) : null;
        }

        if (! $review->isAwaitingAgent()) {
            return null;
        }

        $adopted = DB::transaction(function () use ($review, $workspace, $agent): bool {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isAwaitingAgent()) {
                return false;
            }

            $chain = $this->passChainIds($locked);

            Review::query()->whereKey($chain)->update([
                'workspace_id' => $workspace->id,
                'expires_at' => now()->addDays($workspace->reviewRetentionDays()),
            ]);

            $locked->update(['handed_off_at' => now(), 'handed_off_to' => $agent]);

            return true;
        });

        if (! $adopted) {
            return null;
        }

        $review = $this->findForWorkspace($workspace, $review->public_id);

        if ($review) {
            ReviewHandedOff::dispatch($review);
        }

        return $review;
    }

    /**
     * A review made on the site that an agent in its own workspace has just
     * opened: no move, but the page should stop waiting for one.
     */
    protected function takenBy(Review $review, ?string $agent): Review
    {
        if (! $review->isAwaitingAgent()) {
            return $review;
        }

        $taken = Review::query()
            ->whereKey($review->id)
            ->whereNull('handed_off_at')
            ->update(['handed_off_at' => now(), 'handed_off_to' => $agent]);

        if ($taken === 0) {
            return $review;
        }

        $review->forceFill(['handed_off_at' => now(), 'handed_off_to' => $agent]);
        ReviewHandedOff::dispatch($review);

        return $review;
    }

    /**
     * The owner token in a pasted /r/{token} or /r/{token}/board link on this
     * site, or a bare token.
     */
    protected function ownerTokenFrom(string $value): ?string
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $value)) {
            return $value;
        }

        $parts = parse_url($value);
        $bare = fn (string $host): string => preg_replace('/^www\./i', '', strtolower($host));
        $ours = $bare((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if (! is_array($parts) || ! isset($parts['host'], $parts['path']) || $bare($parts['host']) !== $ours) {
            return null;
        }

        return preg_match('#^/r/([A-Za-z0-9]{40})(?:/board)?/?$#', $parts['path'], $m) ? $m[1] : null;
    }

    /**
     * This review and every later pass built on it, in the same workspace.
     *
     * @return list<int>
     */
    protected function passChainIds(Review $review): array
    {
        $ids = [$review->id];
        $frontier = [$review->id];

        while ($frontier !== []) {
            $frontier = Review::query()
                ->whereIn('parent_id', $frontier)
                ->where('workspace_id', $review->workspace_id)
                ->pluck('id')
                ->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /**
     * Webhooks must be http(s) — https only outside local/testing — and must
     * not point inside our own network. The token holder chooses the target
     * and the payload only holds data they already have, but the request
     * still leaves from this container, so the same private-address rules as
     * every other outbound fetch apply (checked again before each send).
     */
    protected function assertValidWebhookUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowed = app()->environment(['local', 'testing']) ? ['http', 'https'] : ['https'];

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, $allowed, true)) {
            throw ValidationException::withMessages([
                'webhook_url' => 'webhook_url must be a valid '.implode('/', $allowed).' URL.',
            ]);
        }

        if ($reason = OutboundUrl::reasonToReject($url)) {
            throw ValidationException::withMessages([
                'webhook_url' => "webhook_url can't be used: {$reason}.",
            ]);
        }
    }
}
