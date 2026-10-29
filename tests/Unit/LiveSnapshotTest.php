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
    }

    public function test_an_email_gets_no_base(): void
    {
        $out = LiveSnapshot::prepare('<table><tr><td>Hi there</td></tr></table>', null, 'n', '');

        $this->assertStringNotContainsString('<base', $out);
        $this->assertStringContainsString('Hi there', $out);
    }
}
