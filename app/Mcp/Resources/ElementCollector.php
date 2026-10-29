<?php

namespace App\Mcp\Resources;

use App\Services\Capture\CaptureSettleScript;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The element map collector an agent runs in its own browser, so a review
 * made from its screenshots knows the page's elements the way a server
 * capture does. The same code the server runs (CaptureSettleScript), also
 * served at /capture/elements.js for hosts that never show resources.
 */
#[Name('element-collector')]
#[Title('Element map collector')]
#[Description('JavaScript to run with page.evaluate (Playwright or Puppeteer) on the page you screenshot, at the top of the page, right after a full-page shot. It returns the element map to send as image_details[].elements on create_review, or elements on add_screenshot.')]
#[Uri('revisemy://capture/elements.js')]
#[MimeType('text/javascript')]
class ElementCollector extends Resource
{
    public function handle(): Response
    {
        return Response::text(CaptureSettleScript::collector());
    }
}
