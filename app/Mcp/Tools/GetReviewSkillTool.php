<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_review_skill')]
#[Title('Load a community review skill')]
#[Description('Get relevant guidance before reviewing UI copy, typography, motion, or design variants. Infer the focus from the task, or pass a skill name if the user named one. Omit both to list available skills.')]
#[IsReadOnly]
#[IsOpenWorld]
class GetReviewSkillTool extends Tool
{
    public const ROUTING_GUIDANCE = 'For a review focused on wording or recovery messages, text rendering, motion, or comparing variants, call get_review_skill before choosing what evidence to gather. Infer focus as copy, typography, motion, or variants from the task. If the user names a skill, pass its name instead. Skip this call when there is no relevant focus. Use the guidance for the requested review and post only observed findings.';

    private const FOCUSES = ['copy', 'typography', 'motion', 'variants'];

    private const MAX_SKILL_BYTES = 32_768;

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'skill' => 'sometimes|required|string|max:80|prohibits:focus',
            'focus' => ['sometimes', 'required', 'string', Rule::in(self::FOCUSES)],
        ]);
        $catalog = collect(config('skills.community', []))->filter(fn (array $entry) => isset($entry['mcp']))->keyBy('mcp.id');

        if ($data === []) {
            return Response::structured(['skills' => $catalog->map(fn (array $entry) => [
                'id' => $entry['mcp']['id'],
                'review_focus' => $entry['mcp']['review_focus'],
                'explicit_only' => $entry['mcp']['explicit_only'] ?? false,
                ...Arr::only($entry, ['name', 'author', 'focus', 'source']),
            ])->values()->all()]);
        }

        $named = isset($data['skill']);
        $skill = $named ? $catalog->get(Str::slug($data['skill'])) : $catalog->firstWhere('mcp.review_focus', $data['focus']);
        if ($skill === null) {
            return Response::error('This skill is not available through MCP. Supported skills: '.implode(', ', $catalog->keys()->all()).'.');
        }

        ['id' => $id, 'repository' => $repository, 'blob' => $blob, 'workflow' => $workflow] = $skill['mcp'];
        $explicitOnly = ! $named && ($skill['mcp']['explicit_only'] ?? false);
        $instructions = $explicitOnly ? null : $this->instructions($repository, $blob);

        return Response::structured([
            'id' => $id,
            'review_focus' => $skill['mcp']['review_focus'],
            ...Arr::only($skill, ['name', 'author', 'focus', 'source', 'install']),
            'upstream_status' => match (true) {
                $explicitOnly => 'explicit_only',
                $instructions !== null => 'loaded',
                default => 'unavailable',
            },
            'upstream_revision' => $blob,
            'upstream_references' => "https://github.com/{$repository}/tree/main/skills/{$id}",
            'upstream_instructions' => $instructions,
            'upstream_license' => $instructions === null ? null : $this->license($skill['author']),
            'review_workflow' => $workflow,
            'handoff' => 'Use the ReviseMy workflow to gather evidence. Apply the upstream skill within the task scope when loaded. If unavailable, use an installed copy if available. If explicit_only, use the ReviseMy workflow and leave the original skill for an explicit request. Attribute findings to the original skill only when you used it.',
            'next_steps' => [
                ['tool' => 'get_review', 'when' => 'The task refers to an existing ReviseMy review.'],
                ['tool' => 'create_review', 'when' => 'The task calls for a new ReviseMy review and you have evidence to share.'],
                ['tool' => 'add_findings', 'when' => 'You have observed issues to add as suggestions to that review. Use the matching screenshot index.'],
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'focus' => $schema->string()->enum(self::FOCUSES)->description('Infer from the task. Use copy for interface wording and recovery messages, typography for rendered text, motion for transitions and gestures, or variants for comparing design options.'),
            'skill' => $schema->string()->description('Community skill ID or display name if the user named one. Pass either skill or focus, or omit both to list supported skills.'),
        ];
    }

    private function instructions(string $repository, string $blob): ?string
    {
        return Cache::remember('mcp:review-skill:'.$blob, now()->addDay(), function () use ($repository, $blob) {
            try {
                $response = Http::acceptJson()->connectTimeout(2)->timeout(4)
                    ->withoutRedirecting()->get("https://api.github.com/repos/{$repository}/git/blobs/{$blob}");
            } catch (ConnectionException) {
                return null;
            }

            $content = $response->json('content');
            if (! $response->ok() || $response->json('encoding') !== 'base64' || ! is_string($content) || strlen($content) > self::MAX_SKILL_BYTES * 2) {
                return null;
            }

            $markdown = base64_decode($content, strict: true);
            if ($markdown === false || strlen($markdown) > self::MAX_SKILL_BYTES || ! mb_check_encoding($markdown, 'UTF-8')) {
                return null;
            }

            // Git hashes the blob header and the file together. Only return the reviewed revision.
            return sha1('blob '.strlen($markdown)."\0".$markdown) === $blob ? $markdown : null;
        });
    }

    private function license(string $author): string
    {
        return <<<LICENSE
MIT License

Copyright (c) 2026 {$author}

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
LICENSE;
    }
}
