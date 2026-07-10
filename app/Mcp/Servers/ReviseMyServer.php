<?php

namespace App\Mcp\Servers;

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
#[Version('1.1.0')]
#[Instructions('ReviseMy is human-in-the-loop design review for agents on Laravel Cloud. After UI work, call create_review with screenshots (optional page_url). A second-opinion job queues automatically. You may call add_findings as a design-reviewer subagent (suggestion/a11y/polish only) before sharing the review_url. Share the link with the human. Poll get_review until status is approved or changes_requested. Apply human pins first; treat second_opinion as hints only. Never claim the UI is done until the human has weighed in.')]
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

    protected array $prompts = [];
}
