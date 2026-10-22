<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Services\ReviewService;
use App\Support\ElementTarget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * App-only (Visibility::App): the inline review's element picking. The
 * sandboxed frame can't fetch the shot's element list itself, so it asks
 * here: the compact list to hit-test, and for one picked element (or the
 * one a drawn box covers) what the composer shows: its chip, its Inspect
 * rows and the element around it.
 */
#[Name('get_shot_elements')]
#[Title('Get a screenshot’s elements')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents never need this; each pin already carries its element. Returns the element list the inline review uses to let the human pick a part of the page.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
#[IsReadOnly]
class GetShotElementsTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected ReviewService $reviews) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'review_id' => 'required|string',
            'screenshot_id' => 'required|integer',
            'element' => 'nullable|integer|min:0',
            'area' => 'nullable|array',
            'area.x' => 'required_with:area|numeric|between:0,1',
            'area.y' => 'required_with:area|numeric|between:0,1',
            'area.w' => 'required_with:area|numeric|between:0,1',
            'area.h' => 'required_with:area|numeric|between:0,1',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);
        $shot = $review?->screenshots()->whereKey($data['screenshot_id'])->first();

        if (! $shot) {
            return Response::error('No screenshot with that id on this review.');
        }

        // No top-level id: the inline review reads this as data, not a review.
        $content = ['screenshot_id' => $shot->id];

        if (! isset($data['element']) && ! isset($data['area'])) {
            return Response::structured($content + ['elements' => $shot->pickList()]);
        }

        $map = $shot->elementMap();
        $css = $shot->cssSize();

        if (isset($data['element'])) {
            // A pick: the element's own box.
            $box = $map['elements'][$data['element']]['box'] ?? null;
            $area = is_array($box) ? ElementTarget::area(['box' => $box], $css) : null;
            $picked = $data['element'];
        } else {
            // A drawn box: whichever element it covers, the box kept as drawn.
            $area = array_map('floatval', array_intersect_key($data['area'], array_flip(['x', 'y', 'w', 'h'])));
            $picked = null;
        }

        if ($map === null || $area === null) {
            return Response::structured($content + ['target' => null]);
        }

        $target = ElementTarget::fromMap($map, $css, $area['x'] + $area['w'] / 2, $area['y'] + $area['h'] / 2, $picked === null ? $area : null, $picked);

        return Response::structured($content + [
            'target' => $target === null ? null : [
                'index' => $target['index'],
                'kind' => $target['kind'] ?? 'Element',
                'text' => ($target['label'] ?? '') !== '' ? $target['label'] : ($target['text'] ?? ''),
                'selector' => $target['selector'] ?? null,
                'area' => $area,
                'parent' => ElementTarget::parentOf($map, $target['index']),
                'rows' => ElementTarget::inspectRows($target),
            ],
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
            'element' => $schema->integer()->description('Optional index of one element, for its chip and Inspect rows'),
            'area' => $schema->object()->description('Optional drawn box {x, y, w, h} (0–1): the element it covers, if any'),
        ];
    }
}
