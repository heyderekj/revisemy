<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DesignCheckupLoop;
use App\Mcp\Tools\AddFindingsTool;
use App\Mcp\Tools\AddScreenshotTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\ListReviewsTool;
use App\Mcp\Tools\RequestSecondOpinionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('ReviseMy')]
#[Version('1.2.0')]
#[Instructions('ReviseMy is a design checkup loop for agents + humans on Laravel Cloud. Loop: create_review (screenshots) → optional add_findings → share review_url → poll get_review → follow next_action. If changes_requested: apply human marks first (work_packets.pins), then create_review with parent_id and new screenshots for the next pass. If approved: stop. Human marks are authoritative; second_opinion is hints only. Never claim the UI is done while status is pending. Use the design_checkup_loop prompt when starting a checkup.')]
class ReviseMyServer extends Server
{
    protected array $tools = [
        CreateReviewTool::class,
        GetReviewTool::class,
        ListReviewsTool::class,
        AddScreenshotTool::class,
        AddFindingsTool::class,
        RequestSecondOpinionTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [
        DesignCheckupLoop::class,
    ];
}
