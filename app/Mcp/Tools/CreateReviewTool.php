<?php

namespace App\Mcp\Tools;

use App\Exceptions\InsufficientCreditsException;
use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Services\ReviewService;
use App\Support\BrandAssets;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;

#[Name('create_review')]
#[Description('Start or continue a design checkup loop: provide exactly one source — capture_url+page_url (public website), html (email), pdf (slides), or images (local UI as data URLs) — and get a review URL for the human. Pass parent_id after changes_requested to open the next pass with a fresh source. Call add_findings before sharing if you want a subagent critique. In MCP Apps hosts the review renders inline so the human can start marking right away.')]
#[RendersApp(ReviewApp::class)]
class CreateReviewTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected ReviewService $reviews) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'title' => 'required|string|max:160',
            'context' => 'nullable|string|max:5000',
            'type' => 'nullable|string|in:ui,website,presentation,email',
            'page_url' => 'nullable|string|max:2048',
            'webhook_url' => 'nullable|string|max:2048',
            'parent_id' => 'nullable|string',
            'images' => 'nullable|array|min:1|max:5',
            'images.*' => 'required|string',
            'capture_url' => 'nullable|boolean',
            'pdf' => 'nullable|string',
            'html' => 'nullable|string|max:500000',
        ], [
            'title.required' => 'Give the review a short title — what should they look at?',
        ]);

        try {
            $review = $this->reviews->createFromRequest($workspace, $data);
        } catch (InsufficientCreditsException $e) {
            return Response::make(Response::error($e->getMessage()))
                ->withStructuredContent($e->payload());
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not create the review.');
        }

        $payload = $review->toAgentPayload();
        $passLabel = $payload['pass'] > 1 ? " (pass {$payload['pass']})" : '';
        $mark = BrandAssets::appIconUrl();

        return Response::make(Response::text(
            "Review created{$passLabel} — waiting on the human.\n\n".
            "Loop: share the link → human marks + decides → you poll get_review → follow next_action.\n\n".
            "Optional: call add_findings (suggestion/a11y/polish) before sharing.\n\n".
            // Embed the yellow mark so hosts that cache domain favicons still show the current brand.
            "Open this link:\n[![ReviseMy]({$mark})]({$payload['review_url']}) {$payload['review_url']}\n\n".
            "Then poll get_review with id `{$payload['id']}`.\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ))->withStructuredContent($payload);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Short title for this checkup pass')->required(),
            'context' => $schema->string()->description('What should the human look at on this pass? Set a new focus for each pass — do not reuse the previous pass’s notes blindly.'),
            'type' => $schema->string()
                ->enum(['ui', 'website', 'presentation', 'email'])
                ->description('What kind of content this is — ui (default), website, slide (`presentation`), or email. Drives the second-opinion lens: emails get CTA/dark-mode/client checks, slides get slide-density checks, websites get above-the-fold/responsive checks. Follow-up passes inherit the parent type.'),
            'page_url' => $schema->string()->description('Live page URL. Required with capture_url:true for server-side website capture (desktop + mobile). Metadata only when using images/html/pdf — does not trigger capture on its own.'),
            'webhook_url' => $schema->string()->description('Optional https URL POSTed when the human decides (event review.decided, HMAC-signed with the review token) — lets pipelines gate on approval instead of polling. Follow-up passes inherit it.'),
            'parent_id' => $schema->string()->description('Previous review id when opening the next pass after changes_requested'),
            'images' => $schema->array()
                ->items($schema->string()->description('Screenshot as https image URL, data URL, or base64 — not a page URL'))
                ->min(1)
                ->max(5)
                ->description('Local or app UI: 1–5 screenshots as data URLs or base64 (costs 1 credit). Prefer this for localhost — do not send http://localhost to remote capture. For public websites use capture_url. Provide exactly one source: images, capture_url, pdf, or html.'),
            'capture_url' => $schema->boolean()
                ->description('Capture page_url server-side (desktop + mobile; costs 5 credits). Public URLs only — for localhost use images data URLs. Requires REVISEMY_CAPTURE_DRIVER=hosted|browsershot. On [capture_not_configured] or [capture_provider_failed], immediately retry once with images. On [insufficient_credits], call create_checkout.'),
            'pdf' => $schema->string()
                ->description('A PDF as https URL or base64 — rendered one screenshot per page, max 5 (type defaults to slide / `presentation`).'),
            'html' => $schema->string()
                ->description('Raw HTML of an email — rendered at ~600px like a mail client (type defaults to email). Requires REVISEMY_CAPTURE_DRIVER. On [capture_not_configured] or [capture_provider_failed], fall back to images with data URLs.'),
        ];
    }
}
