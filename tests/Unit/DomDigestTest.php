<?php

namespace Tests\Unit;

use App\Support\DomDigest;
use PHPUnit\Framework\TestCase;

class DomDigestTest extends TestCase
{
    public function test_strips_noise_but_keeps_structure_and_copy(): void
    {
        $html = <<<'HTML'
        <html>
        <head><style>.hero { color: red; }</style></head>
        <body>
            <!-- build marker -->
            <script>window.analytics = "tracker";</script>
            <noscript>enable js</noscript>
            <svg viewBox="0 0 10 10"><path d="M0 0 L10 10"/></svg>
            <img src="data:image/png;base64,AAAA" alt="logo">
            <h1 style="font-size:99px">Hero headline</h1>
            <a class="cta" href="/signup">Start free</a>
        </body>
        </html>
        HTML;

        $cleaned = DomDigest::clean($html);

        $this->assertStringContainsString('Hero headline', $cleaned);
        $this->assertStringContainsString('Start free', $cleaned);
        $this->assertStringContainsString('class="cta"', $cleaned);
        $this->assertStringNotContainsString('tracker', $cleaned);
        $this->assertStringNotContainsString('color: red', $cleaned);
        $this->assertStringNotContainsString('build marker', $cleaned);
        $this->assertStringNotContainsString('M0 0 L10 10', $cleaned);
        $this->assertStringNotContainsString('font-size:99px', $cleaned);
        $this->assertStringNotContainsString('base64,AAAA', $cleaned);
    }

    public function test_truncates_to_the_char_budget(): void
    {
        $cleaned = DomDigest::clean('<p>'.str_repeat('word ', 2000).'</p>', 100);

        $this->assertLessThanOrEqual(100 + strlen('<!-- truncated -->'), strlen($cleaned));
        $this->assertStringEndsWith('<!-- truncated -->', $cleaned);
    }
}
