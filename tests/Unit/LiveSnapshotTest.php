<?php

namespace Tests\Unit;

use App\Support\LiveSnapshot;
use PHPUnit\Framework\TestCase;

class LiveSnapshotTest extends TestCase
{
    public function test_nothing_of_the_page_runs_navigates_or_submits(): void
    {
        $html = <<<'HTML'
<!doctype html><html><head>
<meta http-equiv="refresh" content="0;url=https://evil.test">
<base href="https://elsewhere.test/">
<script>alert(1)</script>
</head><body onload="steal()">
<a href="javascript:alert(1)" onclick="go()">Pricing</a>
<form action="https://evil.test/collect"><button formaction="https://evil.test">Send</button></form>
<iframe src="https://evil.test"></iframe><object data="x.swf"></object>
<h1 id="hero">Plans for every team</h1>
</body></html>
HTML;

        $out = LiveSnapshot::prepare($html, 'https://example.com/pricing', 'abc123', 'window.rmLive = 1;');

        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringNotContainsString('onload', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('evil.test', $out);
        $this->assertStringNotContainsString('elsewhere.test', $out);
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('<object', $out);
        $this->assertStringContainsString('<h1 id="hero">Plans for every team</h1>', $out);
        $this->assertStringContainsString('<base href="https://example.com/pricing">', $out);
        $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', $out);
        $this->assertStringContainsString('<script nonce="abc123">window.rmLive = 1;</script>', $out);
        $this->assertSame(1, substr_count($out, '<script'));
        // The rules ride inside the page too, for when it's handed to the frame as srcdoc.
        $this->assertStringContainsString('<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; script-src \'nonce-abc123\'', $out);
        $this->assertStringContainsString("connect-src 'none'; form-action 'none'", $out);
    }

    public function test_media_a_script_loaded_comes_back_still(): void
    {
        $html = '<video class="hero" autoplay loop data-rm-src="https://cdn.test/hero.mp4" data-rm-poster="https://cdn.test/hero.jpg"></video>'
            .'<img alt="lazy" data-rm-src="https://cdn.test/a.jpg" loading="lazy">'
            .'<img alt="kept" src="https://cdn.test/b.jpg" data-rm-src="https://cdn.test/b@2x.jpg">'
            .'<video data-rm-src="javascript:alert(1)"></video>';

        $out = LiveSnapshot::prepare($html, null, 'n', '');

        $this->assertStringContainsString('src="https://cdn.test/hero.mp4#t=0.1"', $out);
        $this->assertStringContainsString('poster="https://cdn.test/hero.jpg"', $out);
        $this->assertStringNotContainsString('autoplay', $out);
        $this->assertStringContainsString('<img alt="lazy" data-rm-src="https://cdn.test/a.jpg" src="https://cdn.test/a.jpg">', $out);
        $this->assertStringContainsString('src="https://cdn.test/b.jpg"', $out);
        $this->assertStringNotContainsString(' src="javascript', $out);
    }

    public function test_a_canvas_becomes_its_picture_and_a_video_keeps_its_frame(): void
    {
        $html = '<canvas class="chart" width="300" height="150" data-rm-src="data:image/png;base64,iVBORw0KGgo="></canvas>'
            .'<canvas data-rm-src="data:text/html;base64,PHNjcmlwdD4="></canvas>'
            .'<video data-rm-src="https://cdn.test/a.mp4" data-rm-time="4.25"></video>';

        $out = LiveSnapshot::prepare($html, null, 'n', '');

        $this->assertStringContainsString('<img class="chart" width="300" height="150" src="data:image/png;base64,iVBORw0KGgo=" alt="">', $out);
        $this->assertStringNotContainsString('data:text/html', $out);
        $this->assertStringContainsString('src="https://cdn.test/a.mp4#t=4.25"', $out);
        // Animations land on their end state, as the capture leaves them.
        $this->assertStringContainsString('animation-duration:0s!important', $out);
    }

    public function test_an_email_gets_no_base(): void
    {
        $out = LiveSnapshot::prepare('<table><tr><td>Hi there</td></tr></table>', null, 'n', '');

        $this->assertStringNotContainsString('<base', $out);
        $this->assertStringContainsString('Hi there', $out);
    }
}
