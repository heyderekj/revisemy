<?php

namespace Tests\Feature;

use App\Support\ToolProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Response;
use Tests\TestCase;

/**
 * A capture takes 20 to 60 seconds. A host that asks for progress hears each
 * step as it happens instead of watching a spinner; one that doesn't gets the
 * same single answer as before.
 */
class ToolProgressTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        Storage::fake('public');
        Queue::fake();
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.capture.driver' => 'hosted',
            'revisemy.capture.endpoint' => 'https://capture.test/screenshot',
            'revisemy.capture.api_key' => 'cap-key',
        ]);

        Http::fake([
            'capture.test/function*' => Http::response('{"error":"no function"}', 400),
            'capture.test/*' => Http::response(hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082')),
        ]);

        return $this->postJson('/api/try-token')->json('token');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function createReview(array $meta = []): array
    {
        return [
            'jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call',
            'params' => [
                'name' => 'create_review',
                'arguments' => ['title' => 'Homepage', 'capture_url' => true, 'page_url' => 'https://example.com'],
                ...($meta === [] ? [] : ['_meta' => $meta]),
            ],
        ];
    }

    public function test_a_host_that_asks_hears_each_step_of_a_capture(): void
    {
        $response = $this->withToken($this->token())
            ->postJson('/mcp/revisemy', $this->createReview(['progressToken' => 'p-1']), ['Accept' => 'application/json, text/event-stream'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $events = collect(explode("\n\n", trim($response->streamedContent())))
            ->map(fn (string $event) => json_decode(substr($event, strlen('data: ')), true));

        $progress = $events->where('method', 'notifications/progress')->pluck('params')->values();

        $this->assertSame('p-1', $progress[0]['progressToken']);
        $this->assertSame(range(1, $progress->count()), $progress->pluck('progress')->all(), 'progress only goes up');
        $this->assertSame('Opening example.com to capture it', $progress[0]['message']);
        $this->assertContains('Capturing desktop (1280px)', $progress->pluck('message'));
        $this->assertContains('Capturing mobile (375px)', $progress->pluck('message'));
        $this->assertSame('Saving the review and its second opinion', $progress->last()['message']);

        $result = $events->last();
        $this->assertSame(7, $result['id']);
        $this->assertFalse($result['result']['isError']);
        $this->assertStringContainsString('/r/', $result['result']['structuredContent']['review_url']);
    }

    public function test_a_host_that_does_not_ask_gets_one_json_answer(): void
    {
        $this->withToken($this->token())
            ->postJson('/mcp/revisemy', $this->createReview(), ['Accept' => 'application/json, text/event-stream'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('result.isError', false);
    }

    public function test_reporting_outside_a_tool_call_does_nothing(): void
    {
        ToolProgress::report('Nobody is listening');

        $steps = iterator_to_array(ToolProgress::stream('t', function () {
            ToolProgress::report('One');
            ToolProgress::report('Two');

            return Response::text('done');
        }), false);

        $this->assertCount(3, $steps);
        $this->assertSame('Two', $steps[1]->content()->toArray()['params']['message']);
        $this->assertSame('mobile (375px)', ToolProgress::viewport('mobile-375'));
    }
}
