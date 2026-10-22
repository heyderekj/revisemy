<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Models\Annotation;
use App\Services\MarkCrop;
use App\Services\ReviewService;
use App\Support\ElementTarget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The agent sees what the person circled, not just its coordinates: a crop of
 * each open mark with the mark drawn on it. Kept off get_review so the record
 * that renders the inline review stays small.
 */
#[Name('look_at_marks')]
#[Title('Look at the marks')]
#[Description('See the marks you are about to fix: returns a cropped picture of each open mark (up to 5), with the person\'s box or point drawn in yellow, and a line saying what they wrote. Pass mark_ids (work_packets.pins[].id) to pick which; by default it shows the open marks on this pass and any carried over, must-fix first. Read-only.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class LookAtMarksTool extends Tool
{
    use ResolvesWorkspace;

    public const MAX_MARKS = 5;

    /** Fix order: must-fix first, then questions, then the rest. */
    protected const ORDER = [
        Annotation::SEVERITY_MUST_FIX => 0,
        Annotation::SEVERITY_QUESTION => 1,
        Annotation::SEVERITY_NIT => 2,
    ];

    public function __construct(
        protected ReviewService $reviews,
        protected MarkCrop $crops,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'mark_ids' => 'nullable|array|max:'.self::MAX_MARKS,
            'mark_ids.*' => 'integer',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        $marks = $this->marks($review->id, $review->parent_id, $data['mark_ids'] ?? null);

        if ($marks->isEmpty()) {
            return Response::text(isset($data['mark_ids'])
                ? 'None of those marks are on this review or the pass before it. Use work_packets.pins[].id, not the M# number.'
                : 'No open marks to look at. Follow next_action.');
        }

        $content = [Response::text(sprintf(
            '%d %s on "%s", pass %d. Yellow shows what the person marked.',
            $marks->count(),
            $marks->count() === 1 ? 'mark' : 'marks',
            $review->title,
            $review->pass,
        ))];

        foreach ($marks as $mark) {
            $crop = $this->crops->for($mark);
            $pass = (int) $mark->screenshot->review_id !== (int) $review->id ? ' (from the pass before)' : '';
            $caption = "M{$mark->number}{$pass} · {$mark->label()} · {$mark->statusLabel()} · id {$mark->id}\n{$mark->body}";

            if (is_array($mark->target)) {
                $caption .= "\nOn: ".ElementTarget::summary($mark->target).(isset($mark->target['selector']) ? " · `{$mark->target['selector']}`" : '');
            }

            if ($crop === null) {
                $content[] = Response::text($caption."\nNo picture for this one: it sits below the part of the page I can crop. Use its area coordinates in work_packets.pins.");

                continue;
            }

            $content[] = Response::text($caption);
            $content[] = Response::image($crop['jpeg'], 'image/jpeg');
        }

        return Response::make($content);
    }

    /**
     * @param  list<int>|null  $ids
     * @return Collection<int, Annotation>
     */
    protected function marks(int $reviewId, ?int $parentId, ?array $ids): Collection
    {
        $query = Annotation::query()
            ->whereHas('screenshot', fn ($q) => $q->whereIn('review_id', array_filter([$reviewId, $parentId])))
            ->with('screenshot');

        if ($ids !== null) {
            return $query->whereKey($ids)->get()->sortBy('number')->values();
        }

        return $query
            ->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS])
            ->get()
            // Reopened marks from the pass before first, then by severity and number.
            ->sortBy(fn (Annotation $mark) => [
                (int) $mark->screenshot->review_id === $reviewId ? 1 : 0,
                self::ORDER[$mark->severity] ?? 3,
                $mark->number,
            ])
            ->take(self::MAX_MARKS)
            ->values();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id')->required(),
            'mark_ids' => $schema->array()->items($schema->integer())->max(self::MAX_MARKS)->description('Optional: which marks to see, by work_packets.pins[].id. Defaults to the open marks, up to 5.'),
        ];
    }
}
