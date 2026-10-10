<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CheckPage;
use App\Mcp\Prompts\DesignCheckupLoop;
use App\Mcp\Resources\ReviewApp;
use App\Mcp\Tools\AddFindingsTool;
use App\Mcp\Tools\AddMarkTool;
use App\Mcp\Tools\AddScreenshotTool;
use App\Mcp\Tools\CancelSubscriptionTool;
use App\Mcp\Tools\CreateCheckoutTool;
use App\Mcp\Tools\CreatePortalTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\DecideReviewTool;
use App\Mcp\Tools\GetBillingTool;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\ListReviewsTool;
use App\Mcp\Tools\RequestSecondOpinionTool;
use App\Mcp\Tools\ResolveMarksTool;
use App\Mcp\Tools\VerifyMarkTool;
use App\Models\User;
use App\Support\AssistantPhrases;
use App\Support\BrandAssets;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Icon;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\Transport;

#[Name('ReviseMy')]
#[Icon(BrandAssets::APP_ICON, mimeType: 'image/png', sizes: ['64x64'])]
#[Icon(BrandAssets::FAVICON_32, mimeType: 'image/png', sizes: ['32x32'])]
#[Icon(BrandAssets::APPLE_TOUCH, mimeType: 'image/png', sizes: ['180x180'])]
class ReviseMyServer extends Server
{
    /**
     * Loaded into every host session alongside all tool schemas, so the
     * billing half is composed in the constructor rather than describing a
     * checkout tool that is not registered.
     */
    protected const BASE_INSTRUCTIONS = 'ReviseMy is a design checkup loop for agents + humans on Laravel Cloud. Loop: create_review with exactly one source — capture_url+page_url (public website), html (email), pdf (slides), or images (local UI as data URLs) — plus optional type (ui, website, presentation/slide, email) for the second-opinion lens → optional add_findings → share review_url → poll get_review → follow next_action. A checkup is not done until create_review returns a review_url; paste that URL in the chat. page_url alone does not capture; use capture_url:true for live pages. Prefer images (data URLs) for localhost — never put a page URL in images. If capture fails, create_review still returns a review_url with no page shot and names [capture_provider_failed] or [capture_not_configured]; add the page with add_screenshot (an image data URL per viewport) before you share the link. If the project has a DESIGN.md (or another design-rules file), pass its text as design_rules on the first create_review so the second opinion checks against it.';

    protected const LOOP_INSTRUCTIONS = 'If changes_requested: apply human marks first (work_packets.pins), and as you fix each one call resolve_marks with its id (status in_progress → resolved with a note). Check the skipped list in the response — any mark named there did NOT land. Humans may also mark resolved on the board; still call resolve_marks when you fix code so notes and after images land. When loop.outstanding_count reaches 0, create_review with parent_id and a fresh source for the next pass. If approved: stop. Human marks are authoritative; second_opinion is hints only. Only the human verifies or reopens marks — never set a mark to verified yourself. Never claim the UI is done while status is pending. Hosts that support MCP Apps render the review inline on create_review and get_review. Every other host does not. Always paste review_url in the reply. Keep polling get_review either way. The add_mark, decide_review, and verify_mark tools are the human UI only — never call them yourself. Use the design_checkup_loop prompt when starting a checkup, or check_page for one live page.';

    /** Checkout, portal, and cancellation only exist when paid pricing is on. */
    protected const PAID_ONLY_TOOLS = [
        CreateCheckoutTool::class,
        CreatePortalTool::class,
        CancelSubscriptionTool::class,
    ];

    protected array $tools = [
        CreateReviewTool::class,
        GetReviewTool::class,
        ListReviewsTool::class,
        AddScreenshotTool::class,
        AddFindingsTool::class,
        ResolveMarksTool::class,
        RequestSecondOpinionTool::class,
        // Always on: it reports remaining credits, which gate every create_review.
        GetBillingTool::class,
        CreateCheckoutTool::class,
        CreatePortalTool::class,
        CancelSubscriptionTool::class,
        AddMarkTool::class,
        DecideReviewTool::class,
        VerifyMarkTool::class,
    ];

    protected array $resources = [
        ReviewApp::class,
    ];

    protected array $prompts = [
        DesignCheckupLoop::class,
        CheckPage::class,
    ];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        // config/revisemy.php is the single source of truth for the public
        // version (homepage badge, /changelog, JSON-LD) — #[Version] can't read
        // config, so it is set here instead of drifting in an attribute.
        $this->version = (string) config('revisemy.version', $this->version);

        // Every tool's name, description, and schema is loaded into the host's
        // context for the whole session. While pricing is paused the checkout
        // tools can only ever return [pricing_disabled], so don't ship them.
        if (! config('billing.pricing_enabled')) {
            $this->tools = array_values(array_diff($this->tools, self::PAID_ONLY_TOOLS));
        }

        // Built per request, after sign-in, so the workspace's own words for
        // asking (and not asking) for ReviseMy come with the defaults.
        $user = auth()->user();

        $this->instructions = implode(' ', [
            AssistantPhrases::instructions($user instanceof User ? $user->workspace : null),
            self::BASE_INSTRUCTIONS,
            $this->billingInstructions(),
            self::LOOP_INSTRUCTIONS,
        ]);
    }

    protected function billingInstructions(): string
    {
        $credits = (int) config('billing.plans.free.credits', 20);
        $burn = collect(config('billing.costs', []))
            ->map(fn (int $cost, string $source) => "{$source}={$cost}")
            ->implode(', ');

        $line = "Credits: Try = {$credits}/mo rolling (no rollover); burn {$burn}.";

        if (! config('billing.pricing_enabled')) {
            // There is no checkout tool to point at, so say so plainly —
            // otherwise the model invents a payment link.
            return $line.' Paid Plus is paused and there is no checkout tool on this server. On [insufficient_credits] call get_billing and tell the human when credits refill; never invent a payment link or ask them to upgrade.';
        }

        return $line.' On [insufficient_credits] call get_billing, offer Plus or a one-time credit pack (never expires), then create_checkout with the chosen product — immediately paste share_markdown into chat rather than only saying “finish payment in the browser”.';
    }
}
