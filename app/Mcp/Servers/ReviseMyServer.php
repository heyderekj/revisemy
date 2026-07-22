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
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Icon;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('ReviseMy')]
#[Version('1.7.0')]
#[Icon('images/app-icon.png', mimeType: 'image/png', sizes: ['256x256'])]
#[Icon('images/favicon-32x32.png', mimeType: 'image/png', sizes: ['32x32'])]
#[Icon('images/apple-touch-icon.png', mimeType: 'image/png', sizes: ['180x180'])]
#[Instructions('ReviseMy is a design checkup loop for agents + humans on Laravel Cloud. Loop: create_review with exactly one source — capture_url+page_url (public website), html (email), pdf (slides), or images (local UI as data URLs) — plus optional type (ui, website, presentation/slide, email) for the second-opinion lens → optional add_findings → share review_url → poll get_review → follow next_action. page_url alone does not capture; use capture_url:true for live pages. Prefer images (data URLs) for localhost — never put a page URL in images. Credits: Free 30/mo, Plus 100/mo ($9 via Paddle); burn images/pdf=1, html=3, capture_url=5; same full quality on both plans. On [insufficient_credits] call get_billing then create_checkout and open checkout_url (Paddle) for the human. To cancel Plus after the human asks, call cancel_subscription with confirm:true (keeps Plus until period end); use create_portal for payment-method / receipt changes. If changes_requested: apply human marks first (work_packets.pins), and as you fix each one call resolve_marks with its id (status in_progress → resolved with a note). Humans may also mark resolved on the board; still call resolve_marks when you fix code so notes and after images land. When loop.outstanding_count reaches 0, create_review with parent_id and a fresh source for the next pass. If approved: stop. Human marks are authoritative; second_opinion is hints only. Only the human verifies or reopens marks — never set a mark to verified yourself. Never claim the UI is done while status is pending. In hosts that support MCP Apps the review renders inline (create_review and get_review) so the human can mark and decide right there; keep polling get_review either way. The add_mark, decide_review, and verify_mark tools are the human UI only — never call them yourself. Use the design_checkup_loop prompt when starting a checkup.')]
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
