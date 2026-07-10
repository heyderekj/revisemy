<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddScreenshotTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\ListReviewsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('ReviseMy')]
#[Version('1.0.0')]
#[Instructions('ReviseMy is human-in-the-loop design review for agents. After UI work, call create_review with screenshots. Share the review_url with the human. Poll get_review until status is approved or changes_requested, then apply pin feedback. Do not claim the UI is done until the human has weighed in.')]
class ReviseMyServer extends Server
{
    protected array $tools = [
        CreateReviewTool::class,
        GetReviewTool::class,
        ListReviewsTool::class,
        AddScreenshotTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
