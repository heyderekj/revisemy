<?php

namespace App\Services\Vision;

use App\Models\Finding;
use App\Models\Screenshot;
use Illuminate\Support\Facades\Http;

class OpenAiVisionProvider extends BaseVisionProvider
{
    public function enabled(): bool
    {
        $key = config('revisemy.openai.api_key');
        $baseUrl = config('revisemy.openai.base_url');

        return (is_string($key) && $key !== '')
            || (is_string($baseUrl) && $baseUrl !== '');
    }

    public function source(): string
    {
        return Finding::SOURCE_OPENAI;
    }

    public function findings(Screenshot $screenshot, string $prompt): array
    {
        $image = $this->imagePayload($screenshot);
        if (! $this->enabled() || ! $image) {
            return [];
        }

        $dataUrl = 'data:'.$image['mime'].';base64,'.$image['base64'];
        $baseUrl = config('revisemy.openai.base_url');
        $customBase = is_string($baseUrl) && $baseUrl !== '';
        $endpoint = rtrim($customBase ? $baseUrl : 'https://api.openai.com/v1', '/').'/chat/completions';

        $payload = [
            'model' => config('revisemy.openai.model', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];

        // Some local/OpenAI-compatible servers reject response_format.
        if (! $customBase) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $request = Http::timeout((int) config('revisemy.openai.timeout', 45));
        $key = config('revisemy.openai.api_key');
        if (is_string($key) && $key !== '') {
            $request = $request->withToken($key);
        }

        $response = $request->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI vision request failed: '.$response->status());
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        return $this->parseFindings($this->decodeJson(is_string($content) ? $content : null));
    }
}
