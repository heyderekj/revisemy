<?php

namespace App\Services;

use App\Events\ReviewDecided;
use App\Models\Review;
use App\Models\Screenshot;
use App\Models\Workspace;
use App\Services\Capture\PageCaptureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        protected ScreenshotStorage $screenshots,
        protected SecondOpinionService $opinions,
        protected PageCaptureService $capture,
        protected DocumentIngestionService $documents,
    ) {}

    /**
     * Create a review from a validated request payload, resolving whichever
     * source was provided: uploaded images, a server-side page capture
     * (capture_url + page_url), a PDF (presentation), or raw HTML (email).
     * Exactly one source is required; type is inferred from the source when
     * not explicit.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromRequest(Workspace $workspace, array $data): Review
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

        $type = $data['type'] ?? null;
        $domHtml = null;

        if (isset($sources['capture_url'])) {
            $pageUrl = trim((string) ($data['page_url'] ?? ''));

            if ($pageUrl === '' || ! filter_var($pageUrl, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'page_url' => 'capture_url needs a valid page_url to render.',
                ]);
            }

            $images = $this->capture->captureUrl($pageUrl);
            $domHtml = $this->capture->captureDom($pageUrl);
            $type ??= Review::TYPE_WEBSITE;
        } elseif (isset($sources['pdf'])) {
            $images = $this->documents->pdfToImages((string) $data['pdf']);
            $type ??= Review::TYPE_PRESENTATION;
        } elseif (isset($sources['html'])) {
            $images = $this->capture->captureHtml((string) $data['html']);
            $domHtml = (string) $data['html'];
            $type ??= Review::TYPE_EMAIL;
        }

        return $this->create(
            $workspace,
            $data['title'],
            $data['context'] ?? null,
            $images,
            $data['page_url'] ?? null,
            $data['parent_id'] ?? null,
            $type,
            $domHtml,
            $data['webhook_url'] ?? null,
        );
    }

    /**
     * @param  list<string|UploadedFile|array{binary: string, meta: array<string, mixed>|null}>  $images
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
            // A pipeline sets the webhook once; follow-up passes inherit it.
            $webhookUrl ??= $parent->webhook_url;
        }

        $review = $workspace->reviews()->create([
            'parent_id' => $parent?->id,
            'title' => $title,
            'context' => $context,
            'type' => $type ?? Review::TYPE_UI,
            'page_url' => $pageUrl,
            'webhook_url' => $webhookUrl,
            'pass' => $pass,
        ]);

        if ($domHtml !== null && trim($domHtml) !== '') {
            try {
                $path = 'reviews/'.$review->public_id.'/dom.html';
                Storage::disk(ScreenshotStorage::diskName())->put($path, substr($domHtml, 0, 2 * 1024 * 1024));
                $review->update(['dom_path' => $path]);
            } catch (\Throwable $e) {
                Log::warning('DOM snapshot persist failed', ['review' => $review->public_id, 'error' => $e->getMessage()]);
            }
        }

        foreach (array_values($images) as $index => $image) {
            $shot = is_array($image)
                ? $this->screenshots->storeRaw($review, $image['binary'], $index, $image['meta'] ?? null)
                : $this->screenshots->store($review, $image, $index);
            $this->opinions->queue($shot);
        }

        return $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent']) ?? $review;
    }

    public function addScreenshot(Review $review, string|UploadedFile $image): Screenshot
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
        $this->opinions->queue($shot);

        return $shot;
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

    public function findForWorkspace(Workspace $workspace, string $publicId): ?Review
    {
        return $workspace->reviews()
            ->where('public_id', $publicId)
            ->with(['screenshots.annotations', 'screenshots.findings', 'parent'])
            ->first();
    }

    /**
     * Webhooks must be http(s) — https only outside local/testing. Same trust
     * stance as page_url capture: the token holder chooses the target, and the
     * payload only contains data that holder already has.
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
    }
}
