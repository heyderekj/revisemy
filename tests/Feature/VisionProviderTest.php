<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use App\Services\SecondOpinionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisionProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    protected function makeScreenshot(): Screenshot
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Vision test',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        return Review::query()->firstOrFail()->screenshots()->firstOrFail();
    }

    protected function anthropicResponse(array $findings): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode(['findings' => $findings])],
            ],
        ];
    }

    public function test_anthropic_provider_persists_vision_findings(): void
    {
        config([
            'revisemy.vision.provider' => 'anthropic',
            'revisemy.anthropic.api_key' => 'test-key',
            'revisemy.openai.api_key' => null,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicResponse([
                ['severity' => 'a11y', 'body' => 'Button contrast is too low against the hero image.', 'area' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.1]],
                ['severity' => 'must-fix', 'body' => 'Severity should be capped to suggestion.', 'area' => null],
                ['severity' => 'polish', 'body' => 'Tiny area should be dropped.', 'area' => ['x' => 0.5, 'y' => 0.5, 'w' => 0.001, 'h' => 0.001]],
            ])),
        ]);

        $screenshot = $this->makeScreenshot();
        app(SecondOpinionService::class)->generate($screenshot);

        $vision = $screenshot->fresh('findings')->findings->where('source', Finding::SOURCE_ANTHROPIC)->values();

        $this->assertCount(3, $vision);
        $this->assertSame('a11y', $vision[0]->severity);
        $this->assertEqualsWithDelta(0.1, $vision[0]->area['x'], 0.001);
        // Unknown severities get capped to suggestion — never must-fix.
        $this->assertSame('suggestion', $vision[1]->severity);
        // Sub-2% areas are treated as noise and dropped to null.
        $this->assertNull($vision[2]->area);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com')
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version');
        });
    }

    public function test_regenerating_replaces_anthropic_findings_without_duplicates(): void
    {
        config([
            'revisemy.vision.provider' => 'anthropic',
            'revisemy.anthropic.api_key' => 'test-key',
            'revisemy.openai.api_key' => null,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicResponse([
                ['severity' => 'polish', 'body' => 'The card shadow is harsh; soften it.', 'area' => null],
            ])),
        ]);

        $screenshot = $this->makeScreenshot();
        $service = app(SecondOpinionService::class);
        $service->generate($screenshot);
        $service->generate($screenshot->fresh());

        $this->assertSame(
            1,
            $screenshot->fresh('findings')->findings->where('source', Finding::SOURCE_ANTHROPIC)->count(),
        );
    }

    public function test_auto_provider_falls_back_to_openai_when_only_its_key_is_set(): void
    {
        config([
            'revisemy.vision.provider' => 'auto',
            'revisemy.anthropic.api_key' => null,
            'revisemy.openai.api_key' => 'openai-key',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['findings' => [
                        ['severity' => 'suggestion', 'body' => 'Make the primary CTA state the outcome.', 'area' => null],
                    ]])]],
                ],
            ]),
        ]);

        $screenshot = $this->makeScreenshot();
        app(SecondOpinionService::class)->generate($screenshot);

        $findings = $screenshot->fresh('findings')->findings;
        $this->assertSame(1, $findings->where('source', Finding::SOURCE_OPENAI)->count());
        $this->assertSame(0, $findings->where('source', Finding::SOURCE_ANTHROPIC)->count());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && $request->hasHeader('Authorization')
                && ($request->data()['response_format']['type'] ?? null) === 'json_object';
        });
    }

    public function test_no_keys_means_checklist_only(): void
    {
        config([
            'revisemy.vision.provider' => 'auto',
            'revisemy.anthropic.api_key' => null,
            'revisemy.openai.api_key' => null,
            'revisemy.openai.base_url' => null,
        ]);

        Http::fake();

        $screenshot = $this->makeScreenshot();
        app(SecondOpinionService::class)->generate($screenshot);

        $sources = $screenshot->fresh('findings')->findings->pluck('source')->unique()->all();
        $this->assertSame([Finding::SOURCE_CHECKLIST], $sources);
        Http::assertNothingSent();
    }

    public function test_custom_base_url_enables_openai_compatible_vision_without_api_key(): void
    {
        config([
            'revisemy.vision.provider' => 'openai',
            'revisemy.anthropic.api_key' => null,
            'revisemy.openai.api_key' => null,
            'revisemy.openai.base_url' => 'http://localhost:11434/v1',
            'revisemy.openai.model' => 'llama3.2-vision',
        ]);

        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['findings' => [
                        ['severity' => 'polish', 'body' => 'Soften the card shadow near the top.', 'area' => ['x' => 0.2, 'y' => 0.1, 'w' => 0.4, 'h' => 0.2]],
                    ]])]],
                ],
            ]),
        ]);

        $screenshot = $this->makeScreenshot();
        app(SecondOpinionService::class)->generate($screenshot);

        $vision = $screenshot->fresh('findings')->findings->where('source', Finding::SOURCE_OPENAI)->values();
        $this->assertCount(1, $vision);
        $this->assertSame('polish', $vision[0]->severity);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/v1/chat/completions'
                && ! $request->hasHeader('Authorization')
                && ! array_key_exists('response_format', $request->data())
                && ($request->data()['model'] ?? null) === 'llama3.2-vision';
        });
    }

    public function test_vision_findings_similar_to_checklist_are_deduped(): void
    {
        config([
            'revisemy.vision.provider' => 'anthropic',
            'revisemy.anthropic.api_key' => 'test-key',
            'revisemy.openai.api_key' => null,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->anthropicResponse([
                // Near-verbatim copy of a ui checklist item — should be dropped.
                ['severity' => 'suggestion', 'body' => 'Check visual hierarchy: is there one clear primary action, or do multiple elements compete for attention here?', 'area' => null],
            ])),
        ]);

        $screenshot = $this->makeScreenshot();
        app(SecondOpinionService::class)->generate($screenshot);

        $this->assertSame(
            0,
            $screenshot->fresh('findings')->findings->where('source', Finding::SOURCE_ANTHROPIC)->count(),
        );
    }
}
