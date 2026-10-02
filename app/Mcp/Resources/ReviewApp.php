<?php

namespace App\Mcp\Resources;

use App\Support\BrandAssets;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Icon;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;
use Illuminate\Support\Facades\Vite;

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
#[Icon(BrandAssets::APP_ICON, mimeType: 'image/png', sizes: ['64x64'])]
class ReviewApp extends AppResource
{
    public function appMeta(): AppMeta
    {
        return AppMeta::make()
            ->csp(Csp::make()->resourceDomains($this->resourceDomains()));
    }

    public function handle(): Response
    {
        return Response::view('mcp.review-app', [
            'styles' => $this->fontFaces().$this->built('resources/css/mcp-app.css'),
            // A literal `</script` inside the bundle would end the inline tag early.
            'script' => str_ireplace('</script', '<\\/script', $this->built('resources/js/mcp-app.js')),
        ]);
    }

    /**
     * The compiled stylesheet and the bundled Alpine, inlined.
     *
     * Same tokens as the site (resources/css/tokens.css), so the inline
     * review can't drift from the web one, and nothing loads from a CDN a
     * host's CSP might refuse. A missing build reads as empty rather than
     * failing the resource: the review still arrives, unstyled.
     */
    protected function built(string $entry): string
    {
        return (string) rescue(fn () => Vite::content($entry), '', report: false);
    }

    /**
     * Figtree at the two weights the review uses, as data URIs: `/fonts/...`
     * doesn't resolve inside the sandbox. If a host refuses `data:` fonts the
     * system face takes over, which is harmless.
     */
    protected function fontFaces(): string
    {
        return once(function () {
            $css = '';

            foreach ([400, 600] as $weight) {
                $path = public_path("fonts/figtree-latin-{$weight}.woff2");

                if (! is_file($path)) {
                    continue;
                }

                $data = base64_encode((string) file_get_contents($path));
                $css .= "@font-face{font-family:'Figtree';font-style:normal;font-weight:{$weight};font-display:swap;src:url(data:font/woff2;base64,{$data}) format('woff2');}";
            }

            return $css;
        });
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
