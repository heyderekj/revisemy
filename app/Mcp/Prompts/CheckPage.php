<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * One pick from the host's prompt menu to check a live page, whatever words
 * the person would otherwise use. The full loop lives in design_checkup_loop.
 */
#[Name('check_page')]
#[Description('Capture a live web page on desktop, mobile and tablet and open a ReviseMy review of it.')]
class CheckPage extends Prompt
{
    public function handle(Request $request): Response
    {
        $url = trim((string) $request->get('url', ''));

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return Response::error('Give check_page a full public address, like https://example.com.');
        }

        $focus = trim(str_replace('"', "'", (string) $request->get('focus', '')));
        $context = $focus !== '' ? $focus : 'First look: hierarchy above the fold, spacing and type, and anything off on mobile.';

        return Response::text(<<<PROMPT
Run a ReviseMy design checkup on {$url}.

1. Tell me you're capturing the page; it takes 20 to 60 seconds.
2. Call `create_review` with `capture_url: true`, `page_url: "{$url}"`, `type: "website"`, a short title, and `context: "{$context}"`.
3. Share the `review_url` it returns, even if the review also shows here.
4. Poll `get_review` and follow `next_action` until I approve. Apply my marks when I ask for changes, then open the next pass with `parent_id`. Never mark your own work verified.
PROMPT);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(name: 'url', description: 'The public page to check, like https://example.com', required: true),
            new Argument(name: 'focus', description: 'What to look at on this pass (optional)', required: false),
        ];
    }
}
