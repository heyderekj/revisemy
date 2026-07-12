<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DesignCheckupLoop;
use App\Mcp\Resources\ReviewApp;
use App\Mcp\Tools\AddFindingsTool;
use App\Mcp\Tools\AddMarkTool;
use App\Mcp\Tools\AddScreenshotTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\DecideReviewTool;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\ListReviewsTool;
use App\Mcp\Tools\RequestSecondOpinionTool;
use App\Mcp\Tools\ResolveMarksTool;
use App\Mcp\Tools\VerifyMarkTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('ReviseMy')]
#[Version('1.6.0')]
#[Instructions('ReviseMy is a design checkup loop for agents + humans on Laravel Cloud. Loop: create_review (screenshots, plus a type — ui, website, presentation/slide, or email — so the second opinion uses the right lens) → optional add_findings → share review_url → poll get_review → follow next_action. If changes_requested: apply human marks first (work_packets.pins), and as you fix each one call resolve_marks with its id (status in_progress → resolved with a note). Humans may also mark resolved on the board; still call resolve_marks when you fix code so notes and after images land. When loop.outstanding_count reaches 0, create_review with parent_id and new screenshots for the next pass. If approved: stop. Human marks are authoritative; second_opinion is hints only. Only the human verifies or reopens marks — never set a mark to verified yourself. Never claim the UI is done while status is pending. In hosts that support MCP Apps the review renders inline (create_review and get_review) so the human can mark and decide right there; keep polling get_review either way. The add_mark, decide_review, and verify_mark tools are the human UI only — never call them yourself. Use the design_checkup_loop prompt when starting a checkup.')]
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
