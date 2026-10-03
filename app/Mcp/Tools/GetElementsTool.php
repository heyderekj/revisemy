<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Services\ReviewService;
use App\Support\ElementAnchor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * App-only (Visibility::App): the inline review loads a capture's element
 * map so marks can snap to page elements. The app's CSP only allows images,
 * so the map comes through the bridge rather than a fetch. Kept off the
 * agent's payload — it's hundreds of boxes the model never needs.
 */
#[Name('get_elements')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents must never call this. Loads the element boxes of a capture for the inline review app.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
#[IsReadOnly]
#[IsOpenWorld(false)]
class GetElementsTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'review_id' => 'required|string',
            'screenshot_id' => 'required|integer',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);
        $screenshot = $review?->screenshots()->whereKey($data['screenshot_id'])->first();

        if (! $screenshot) {
            return Response::error('No screenshot with that id on this review.');
        }

        return Response::structured([
            'screenshot_id' => $screenshot->id,
            'elements' => ElementAnchor::forCanvas($screenshot),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->description('The review public id')->required(),
            'screenshot_id' => $schema->integer()->description('The screenshot id from the review payload')->required(),
        ];
    }
}
