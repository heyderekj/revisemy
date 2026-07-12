<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Server\Ui\Enums\Library;

/**
 * The inline review UI rendered by MCP Apps hosts (Claude web/desktop, etc.)
 * whenever create_review or get_review runs. Shows screenshots with marks and
 * lets the human mark and decide without leaving the chat, via the app-only
 * add_mark / decide_review / verify_mark tools.
 */
#[Name('review-app')]
#[Title('ReviseMy review')]
#[Description('Interactive inline design review: screenshots with marks, a mark composer, and approve / request changes controls for the human.')]
#[Uri('ui://revisemy/review-app')]
class ReviewApp extends AppResource
{
    public function appMeta(): AppMeta
    {
        return AppMeta::make()
            ->csp(Csp::make()->resourceDomains([
                ...$this->resourceDomains(),
                // Instrument Sans, same face the review page loads via Bunny.
                'https://fonts.bunny.net',
            ]))
            ->libraries(Library::Tailwind, Library::Alpine);
    }

    public function handle(): Response
    {
        return Response::view('mcp.review-app', [
            'libraryScripts' => $this->libraryScripts(),
        ]);
    }

    /**
     * Origins the sandboxed iframe may load resources from: the app itself
     * (screenshots on the public disk) plus the screenshot disk's own origin
     * when it lives on object storage (Laravel Cloud). Override with
     * revisemy.mcp_app.resource_domains when the derived list is wrong.
     *
     * @return array<int, string>
     */
    protected function resourceDomains(): array
    {
        $configured = config('revisemy.mcp_app.resource_domains');

        if (is_array($configured) && $configured !== []) {
            return array_values($configured);
        }

        $origins = [$this->origin(config('app.url'))];

        $disk = (string) config('filesystems.revisemy_disk', config('filesystems.default', 'public'));
        $origins[] = $this->origin(config("filesystems.disks.{$disk}.url"));

        return array_values(array_unique(array_filter($origins)));
    }

    protected function origin(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
