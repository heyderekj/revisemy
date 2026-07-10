<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\ReviewService;
use App\Services\SecondOpinionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_findings')]
#[Description('Act as a design-reviewer subagent: push suggestion/a11y/polish findings into an open review for the human to see alongside their pins. Never use must-fix — human pins stay authoritative.')]
class AddFindingsTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
        protected SecondOpinionService $opinions,
    ) {}

    public function handle(Request $request): Response
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'findings' => 'required|array|min:1|max:20',
            'findings.*.body' => 'required|string|max:2000',
            'findings.*.severity' => 'nullable|string|in:suggestion,a11y,polish',
            'findings.*.screenshot_index' => 'nullable|integer|min:0|max:4',
            'findings.*.related_pin' => 'nullable|integer|min:1',
            'findings.*.area' => 'nullable|array',
            'findings.*.area.x' => 'nullable|numeric',
            'findings.*.area.y' => 'nullable|numeric',
            'findings.*.area.w' => 'nullable|numeric',
            'findings.*.area.h' => 'nullable|numeric',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        try {
            $created = $this->opinions->addAgentFindings($review, $data['findings']);
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not add findings.');
        }

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings'])?->toAgentPayload();

        return Response::text(
            'Added '.count($created)." agent finding(s) to the review for the human.\n\n".
            "Remember: these are suggestions only. Wait for the human decision via get_review.\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id')->required(),
            'findings' => $schema->array()
                ->items($schema->object())
                ->min(1)
                ->max(20)
                ->description('List of {severity?, body, area?, screenshot_index?, related_pin?} — severity must be suggestion|a11y|polish')
                ->required(),
        ];
    }
}
