<?php

namespace App\Http\Controllers;

use App\Models\AnnotationComment;
use App\Models\FeedbackRequest;
use App\Services\MarkLifecycleService;
use App\Support\FeedbackText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FeedbackRequestController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $feedback = FeedbackRequest::accessible($token);
        $mark = $feedback->annotation;
        $shots = $feedback->review->screenshots;
        $shot = $shots->firstWhere('id', $request->integer('shot'))
            ?? $shots->firstWhere('id', $mark?->screenshot_id)
            ?? $shots->first();

        return response()->view('feedback-request', compact('feedback', 'mark', 'shots', 'shot'), headers: [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function reply(Request $request, string $token, MarkLifecycleService $lifecycle): RedirectResponse
    {
        $feedback = FeedbackRequest::accessible($token);
        $name = $request->input('name');
        $response = $request->input('response');
        $request->merge([
            'name' => is_string($name) ? FeedbackText::sanitizeName($name) : $name,
            'response' => is_string($response) ? FeedbackText::sanitizeBody($response) : $response,
        ]);
        $data = $request->validate([
            'name' => FeedbackText::nameRules(),
            'response' => FeedbackText::bodyRules(),
        ], FeedbackText::nameMessages('name'));

        DB::transaction(function () use ($feedback, $data, $lifecycle): void {
            $current = FeedbackRequest::query()->lockForUpdate()->findOrFail($feedback->id);
            abort_unless($current->isOpen() && ! $current->review->isExpired(), 404);

            if ($current->responded_at || ! $current->review->allowsComments()) {
                return;
            }

            if ($mark = $current->annotation) {
                $lifecycle->addComment($mark, AnnotationComment::AUTHOR_GUEST, $data['response'], $data['name']);
            }

            $current->update([
                'respondent' => $data['name'],
                'response' => $data['response'],
                'responded_at' => now(),
            ]);
        }, 3);

        return redirect()->to($feedback->url());
    }
}
