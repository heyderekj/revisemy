<?php

namespace App\Services\Vision;

use App\Models\Finding;
use App\Models\Screenshot;
use Illuminate\Support\Facades\Http;

class AnthropicVisionProvider extends BaseVisionProvider
{
    public function enabled(): bool
    {
        $key = config('revisemy.anthropic.api_key');

        return is_string($key) && $key !== '';
    }

    public function source(): string
    {
        return Finding::SOURCE_ANTHROPIC;
    }

    public function findings(Screenshot $screenshot, string $prompt): array
    {
        $image = $this->imagePayload($screenshot);
        if (! $this->enabled() || ! $image) {
            return [];
        }

        $response = Http::withHeaders([
            'x-api-key' => (string) config('revisemy.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout((int) config('revisemy.anthropic.timeout', 60))
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('revisemy.anthropic.model', 'claude-opus-4-8'),
                'max_tokens' => 2048,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $image['mime'],
                                    'data' => $image['base64'],
                                ],
                            ],
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic vision request failed: '.$response->status());
        }

        $content = data_get($response->json(), 'content.0.text');

        return $this->parseFindings($this->decodeJson(is_string($content) ? $content : null));
    }
}
