<?php

namespace App\Mcp\Servers;

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
use App\Support\BrandAssets;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Icon;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('ReviseMy')]
#[Version('1.7.4')]
#[Icon(BrandAssets::APP_ICON, mimeType: 'image/png', sizes: ['64x64'])]
#[Icon(BrandAssets::FAVICON_32, mimeType: 'image/png', sizes: ['32x32'])]
#[Icon(BrandAssets::APPLE_TOUCH, mimeType: 'image/png', sizes: ['180x180'])]
#[Instructions('ReviseMy is a design checkup loop for agents + humans on Laravel Cloud. Loop: create_review with exactly one source — capture_url+page_url (public website), html (email), pdf (slides), or images (local UI as data URLs) — plus optional type (ui, website, presentation/slide, email) for the second-opinion lens → optional add_findings → share review_url → poll get_review → follow next_action. page_url alone does not capture; use capture_url:true for live pages. Prefer images (data URLs) for localhost — never put a page URL in images. Credits: Try = 20/mo rolling (no rollover); burn images/pdf=1, html=3, capture_url=5. Paid Plus checkout is paused ([pricing_disabled]) — on [insufficient_credits] call get_billing and tell the human when credits refill; do not invent a payment link or ask them to upgrade. If create_checkout succeeds (pricing re-enabled), immediately paste share_markdown into chat. If changes_requested: apply human marks first (work_packets.pins), and as you fix each one call resolve_marks with its id (status in_progress → resolved with a note). Humans may also mark resolved on the board; still call resolve_marks when you fix code so notes and after images land. When loop.outstanding_count reaches 0, create_review with parent_id and a fresh source for the next pass. If approved: stop. Human marks are authoritative; second_opinion is hints only. Only the human verifies or reopens marks — never set a mark to verified yourself. Never claim the UI is done while status is pending. In hosts that support MCP Apps the review renders inline (create_review and get_review) so the human can mark and decide right there; keep polling get_review either way. The add_mark, decide_review, and verify_mark tools are the human UI only — never call them yourself. Use the design_checkup_loop prompt when starting a checkup.')]
class ReviseMyServer extends Server
{
    protected array $tools = [
        CreateReviewTool::class,
        GetReviewTool::class,
        ListReviewsTool::class,
        AddScreenshotTool::class,
        AddFindingsTool::class,
        ResolveMarksTool::class,
        RequestSecondOpinionTool::class,
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
    ];
}
