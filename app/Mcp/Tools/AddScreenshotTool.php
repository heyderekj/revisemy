<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('add_screenshot')]
#[Title('Add a screenshot')]
#[Description('Append another screenshot to an open design review that is still waiting on feedback.')]
#[IsDestructive(false)]
#[IsOpenWorld]
class AddScreenshotTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected ReviewService $reviews) {}

    public function handle(Request $request): Response
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'image' => 'required|string',
            'dpr' => 'nullable|numeric|between:0.5,4',
            'elements' => 'nullable',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        try {
            $this->reviews->addScreenshot(
                $review,
                $data['image'],
                isset($data['dpr']) ? (float) $data['dpr'] : null,
                ReviewService::elementsFrom($data['elements'] ?? null),
            );
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not add that screenshot.');
        }

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings'])?->toAgentPayload();

        return Response::text(
            "Screenshot added.\n\n".
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id')->required(),
            'image' => $schema->string()->description('Screenshot as https URL, data URL, or base64')->required(),
            'dpr' => $schema->number()->description('Optional device pixels per CSS px (2 for a retina screenshot)'),
            'elements' => $schema->object()->description('Optional element map from the script at '.url('/capture/elements.js').', run in the browser you screenshot. Lets the human mark a whole element and tells you its selector.'),
        ];
    }
}
