<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\BillingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('cancel_subscription')]
#[Description('Cancel Plus for this workspace (stops renewal; keeps Plus until the current period ends, then Try with leftover credits only — no new grant). Requires confirm:true after the human asks to cancel. For payment-method or receipt changes, use create_portal instead (Paddle).')]
class CancelSubscriptionTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected BillingService $billing) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        if (! $request->boolean('confirm')) {
            return Response::error(
                '[confirm_required] Set confirm:true only after the human explicitly asks to cancel Plus. They keep access until the period ends, then Try with no new credit grant.'
            );
        }

        try {
            $this->billing->cancelPro($workspace);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $status = $this->billing->status($workspace->fresh());
        $payload = [
            'canceled' => true,
            'plan' => $status['plan'],
            'plan_name' => $status['plan_name'],
            'credits_remaining' => $status['credits_remaining'],
            'next_action' => 'tell_human',
            'hint' => 'Plus cancellation is scheduled. They keep Plus until the current period ends, then Try (no monthly refill). Payment method changes still go through create_portal / Paddle.',
        ];

        return Response::make(Response::text(
            "Plus cancellation scheduled for this workspace.\n".
            "They keep Plus until the current billing period ends, then return to Try (no new credit grant).\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ))->withStructuredContent($payload);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'confirm' => $schema->boolean()
                ->description('Must be true. Only set after the human explicitly asks to cancel Plus.'),
        ];
    }
}
