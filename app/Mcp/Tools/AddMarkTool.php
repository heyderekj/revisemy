<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Models\Annotation;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Support\FeedbackText;
use App\Support\NormalizedArea;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * App-only (Visibility::App): invoked by the human through the inline review
 * UI, never by the model. Human marks are authoritative — an agent must never
 * call this to plant marks. Visibility hides it from the model's tool list;
 * the token holder is the human, matching the token-gated web review page.
 */
#[Name('add_mark')]
#[Title('Add a mark')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents must never call this. Drops a human mark (M#) on a screenshot from the inline review app.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class AddMarkTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
        protected MarkLifecycleService $lifecycle,
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
            'x' => 'required|numeric|between:0,1',
            'y' => 'required|numeric|between:0,1',
            'area' => 'nullable|array',
            'area.x' => 'required_with:area|numeric|between:0,1',
            'area.y' => 'required_with:area|numeric|between:0,1',
            'area.w' => 'required_with:area|numeric|between:'.NormalizedArea::MIN_MARK_SIDE.',1',
            'area.h' => 'required_with:area|numeric|between:'.NormalizedArea::MIN_MARK_SIDE.',1',
            'element' => 'nullable|integer|min:0',
            'selector' => 'nullable|string|max:300',
            'snap' => 'nullable|boolean',
            'severity' => 'required|in:'.implode(',', Annotation::severities()),
            'body' => FeedbackText::bodyRules(),
            'suggested_copy' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);

        if (! $review) {
            return Response::error('No review with that id for this token.');
        }

        if (! $review->isOpenForFeedback()) {
            return Response::error('This review is no longer open for feedback.');
        }

        $screenshot = $review->screenshots()->whereKey($data['screenshot_id'])->first();

        if (! $screenshot) {
            return Response::error('No screenshot with that id on this review.');
        }

        $body = FeedbackText::sanitizeBody($data['body']);

        if ($body === '') {
            return Response::error('Leave a note on this spot.');
        }

        $suggestedCopy = isset($data['suggested_copy'])
            ? FeedbackText::sanitizeBody($data['suggested_copy'])
            : null;

        $mark = $this->lifecycle->createMark(
            $screenshot,
            (float) $data['x'],
            (float) $data['y'],
            isset($data['area']) ? [
                'x' => (float) $data['area']['x'],
                'y' => (float) $data['area']['y'],
                'w' => (float) $data['area']['w'],
                'h' => (float) $data['area']['h'],
            ] : null,
            $data['severity'],
            $body,
            [
                'suggested_copy' => $suggestedCopy !== '' ? $suggestedCopy : null,
                'source' => Annotation::SOURCE_HUMAN,
                'element' => isset($data['element']) ? (int) $data['element'] : ($data['selector'] ?? null),
                'snap' => (bool) ($data['snap'] ?? true),
            ],
        );

        return Response::structured($review->refresh()->toAgentPayload())
            ->withMeta('revisemy', ['created_mark_id' => $mark->id, 'created_mark_number' => $mark->number]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->description('The review public id')->required(),
            'screenshot_id' => $schema->integer()->description('The screenshot id from the review payload')->required(),
            'x' => $schema->number()->description('Normalized x of the mark point (0–1)')->required(),
            'y' => $schema->number()->description('Normalized y of the mark point (0–1)')->required(),
            'area' => $schema->object()->description('Optional normalized rectangle {x, y, w, h} (top-left origin)'),
            'severity' => $schema->string()->enum(Annotation::severities())->description('must-fix, nit, question, or keep')->required(),
            'body' => $schema->string()->description('The human note for this mark')->required(),
            'suggested_copy' => $schema->string()->description('Optional exact copy string for the agent to apply'),
            'element' => $schema->integer()->description('Optional index of the element the person picked, from get_shot_elements; the mark snaps to its box'),
            'selector' => $schema->string()->description('Optional selector of the picked element, when there is no index'),
            'snap' => $schema->boolean()->description('False keeps the mark free of any element (default true)'),
        ];
    }
}
