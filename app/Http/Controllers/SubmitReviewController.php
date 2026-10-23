<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Services\TryTokenGate;
use App\Services\TryTokenService;
use App\Support\OutboundUrl;
use App\Support\ToolProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Starting a review on the site, without an agent: paste a link or drop in
 * screenshots, a deck or an email. The browser gets a try workspace the
 * same way Connect makes one, so a later Connect from here lands in it, and
 * the review link hands the board to any agent (ReviewService::findOrAdopt).
 */
class SubmitReviewController extends Controller
{
    /** The form field each kind of source arrives in. */
    protected const FIELDS = [
        'link' => 'page_url',
        'images' => 'images',
        'pdf' => 'pdf',
        'html' => 'html',
    ];

    public function show(Request $request): View
    {
        return view('new-review', [
            'kind' => in_array($request->query('kind'), array_keys(self::FIELDS), true) ? $request->query('kind') : 'link',
            'pageUrl' => is_string($request->query('url')) ? $request->query('url') : '',
            'costs' => config('billing.costs'),
            'credits' => $this->browserUser()?->workspace?->totalCredits(),
        ]);
    }

    public function store(Request $request, ReviewService $reviews, TryTokenService $tryTokens, TryTokenGate $gate): RedirectResponse|JsonResponse|StreamedResponse
    {
        $kind = $request->input('kind');

        $validator = Validator::make($request->all(), [
            'kind' => ['required', Rule::in(array_keys(self::FIELDS))],
            'page_url' => ['exclude_unless:kind,link', 'required', 'string', 'max:2048'],
            'images' => ['exclude_unless:kind,images', 'required', 'array', 'min:1', 'max:5'],
            'images.*' => ['exclude_unless:kind,images', 'file', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
            'pdf' => ['exclude_unless:kind,pdf', 'required', 'file', 'mimes:pdf', 'max:20480'],
            'html' => ['exclude_unless:kind,html', 'required', 'file', 'mimes:html,htm,txt', 'max:500'],
            'context' => ['nullable', 'string', 'max:5000'],
        ], [
            'page_url.required' => 'Paste the address of the page to review.',
            'images.required' => 'Add at least one screenshot.',
            'images.max' => 'Keep it to 5 screenshots per review.',
            'images.*.mimes' => 'Screenshots need to be PNG, JPG or WebP.',
            'images.*.max' => 'Each screenshot needs to be under 8MB.',
            'pdf.required' => 'Add the PDF to review.',
            'pdf.mimes' => 'Slides need to be a PDF.',
            'pdf.max' => 'The PDF needs to be under 20MB.',
            'html.required' => 'Add the email’s HTML file.',
            'html.mimes' => 'The email needs to be an .html file.',
            'html.max' => 'The email’s HTML needs to be under 500KB.',
        ]);

        if ($validator->fails()) {
            return $this->retry($request, is_string($kind) ? $kind : 'link', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $field = self::FIELDS[$kind];

        if ($kind === 'link') {
            $data['page_url'] = $this->withScheme(trim($data['page_url']));

            if ($reason = OutboundUrl::reasonToReject($data['page_url'])) {
                return $this->retry($request, $kind, ['page_url' => 'That page can’t be captured — '.$reason.'.']);
            }
        }

        try {
            $gate->assertCanSubmit($request);
        } catch (RuntimeException $e) {
            return $this->retry($request, $kind, [$field => $e->getMessage()]);
        }

        $user = $this->browserUser();

        if (! $user) {
            // The same order Connect uses, so a later Connect from this
            // browser reuses this workspace instead of making another.
            $user = $tryTokens->createWorkspaceUser();
            Auth::guard('web')->login($user, remember: true);
            $request->session()->regenerate();
        }

        // Read up front: uploads are only on disk while the request is.
        $attributes = [
            'title' => $this->title($kind, $data),
            'context' => $data['context'] ?? null,
            ...$this->source($kind, $data),
        ];
        $make = fn () => $reviews->createFromRequest($user->workspace, $attributes, Review::ORIGIN_WEB);

        if ($this->wantsSteps($request)) {
            return $this->streamSteps($make, $field);
        }

        try {
            $review = $make();
        } catch (InsufficientCreditsException|ValidationException $e) {
            return $this->retry($request, $kind, [$field => $this->problem($e)]);
        }

        return redirect()->route('reviews.show', $review->token);
    }

    /**
     * The form's progress dialog asks for the steps as they happen, so a
     * minute-long capture shows which viewports have landed instead of a
     * button that just says it's busy. Without JS the form posts as usual.
     */
    protected function wantsSteps(Request $request): bool
    {
        return str_contains((string) $request->header('Accept'), 'text/event-stream');
    }

    /**
     * Make the review while streaming each step ToolProgress hears, framed
     * like the MCP transport (data: {json}, a blank line), then where the
     * review is or what went wrong. The browser is already signed in: the
     * session is saved before this body runs, so nothing here may touch it.
     *
     * @param  callable(): Review  $make
     */
    protected function streamSteps(callable $make, string $field): StreamedResponse
    {
        return response()->stream(function () use ($make, $field): void {
            // A closed tab still gets its review, listed under Your reviews.
            ignore_user_abort(true);

            $send = function (array $event): void {
                echo 'data: '.json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            $steps = ToolProgress::steps($make);

            try {
                foreach ($steps as $report) {
                    $send($report);
                }

                $send(['done' => route('reviews.show', $steps->getReturn()->token)]);
            } catch (InsufficientCreditsException|ValidationException $e) {
                $send(['errors' => [$field => $this->problem($e)]]);
            } catch (Throwable $e) {
                report($e);
                $send(['errors' => [$field => 'Something went wrong making the review. Your credits are back, so try again.']]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** What to tell the person when the review couldn't be made. */
    protected function problem(InsufficientCreditsException|ValidationException $e): string
    {
        if ($e instanceof InsufficientCreditsException) {
            return sprintf(
                'That needs %d credit%s and this browser’s try workspace has %d left. Credits renew monthly.',
                $e->required,
                $e->required === 1 ? '' : 's',
                $e->remaining,
            );
        }

        return (string) collect($e->errors())->flatten()->first();
    }

    /**
     * Back to /new on the same tab, whether the form was there or in the
     * home hero, with what was typed. Files have to be picked again. The
     * progress dialog gets the errors as JSON and keeps the form as it is.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function retry(Request $request, string $kind, array $errors): RedirectResponse|JsonResponse
    {
        if ($this->wantsSteps($request)) {
            return response()->json(['errors' => $errors], 422);
        }

        return redirect()
            ->route('reviews.new', ['kind' => array_key_exists($kind, self::FIELDS) ? $kind : 'link'])
            ->withInput($request->only('page_url', 'context'))
            ->withErrors($errors);
    }

    protected function browserUser(): ?User
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->workspace ? $user : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function source(string $kind, array $data): array
    {
        return match ($kind) {
            'link' => ['capture_url' => true, 'page_url' => $data['page_url']],
            'images' => ['images' => array_values($data['images'])],
            'pdf' => ['pdf' => 'data:application/pdf;base64,'.base64_encode((string) $data['pdf']->get())],
            'html' => ['html' => (string) $data['html']->get()],
        };
    }

    /**
     * The page's host for a link, else the first file's name.
     *
     * @param  array<string, mixed>  $data
     */
    protected function title(string $kind, array $data): string
    {
        $file = match ($kind) {
            'images' => $data['images'][0] ?? null,
            'pdf' => $data['pdf'],
            'html' => $data['html'],
            default => null,
        };

        $title = $file instanceof UploadedFile
            ? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            : preg_replace('/^www\./i', '', (string) parse_url($data['page_url'] ?? '', PHP_URL_HOST));

        return Str::limit(trim($title) !== '' ? trim($title) : 'Untitled review', 160, '');
    }

    /** Someone pasting example.com means https://example.com. */
    protected function withScheme(string $url): string
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'https://'.$url;
    }
}
