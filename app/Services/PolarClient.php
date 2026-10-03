<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The handful of Polar API calls ReviseMy needs, plus webhook verification.
 * Polar is the merchant of record; credits and plan state live in our DB.
 */
class PolarClient
{
    /** Reject webhooks whose timestamp is further than this from now. */
    protected const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function configured(): bool
    {
        return filled(config('billing.polar.access_token'))
            && filled(config('billing.polar.webhook_secret'))
            && filled($this->productId('plus'));
    }

    public function baseUrl(): string
    {
        return config('billing.polar.server') === 'sandbox'
            ? 'https://sandbox-api.polar.sh'
            : 'https://api.polar.sh';
    }

    public function productId(string $key): ?string
    {
        $id = config("billing.polar.products.{$key}");

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** Reverse of productId(): our product key for a Polar product ID. */
    public function productKey(?string $productId): ?string
    {
        if (! $productId) {
            return null;
        }

        foreach ((array) config('billing.polar.products', []) as $key => $id) {
            if ($id === $productId) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function createCheckout(
        string $productId,
        string $externalCustomerId,
        string $successUrl,
        string $returnUrl,
        ?string $email = null,
        array $metadata = [],
    ): array {
        $body = array_filter([
            'products' => [$productId],
            'external_customer_id' => $externalCustomerId,
            'customer_email' => $email,
            'success_url' => $successUrl,
            'return_url' => $returnUrl,
            'metadata' => $metadata ?: null,
        ], fn ($value) => $value !== null);

        $data = $this->send(fn (PendingRequest $http) => $http->post('/v1/checkouts/', $body));

        return ['id' => (string) $data['id'], 'url' => (string) $data['url']];
    }

    /** One-off signed URL into Polar's customer portal (receipts, card, cancel). */
    public function customerPortalUrl(string $externalCustomerId): string
    {
        $data = $this->send(fn (PendingRequest $http) => $http->post('/v1/customer-sessions/', [
            'external_customer_id' => $externalCustomerId,
        ]));

        return (string) $data['customer_portal_url'];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelAtPeriodEnd(string $subscriptionId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->patch(
            '/v1/subscriptions/'.rawurlencode($subscriptionId),
            ['cancel_at_period_end' => true],
        ));
    }

    /**
     * Verify a Standard Webhooks signature and return the decoded event.
     * Returns null when the signature, timestamp, or body is bad.
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhook(Request $request): ?array
    {
        $secret = (string) config('billing.polar.webhook_secret');
        $id = (string) $request->header('webhook-id');
        $timestamp = (string) $request->header('webhook-timestamp');
        $signatures = (string) $request->header('webhook-signature');
        $body = $request->getContent();

        if ($secret === '' || $id === '' || ! ctype_digit($timestamp) || $signatures === '') {
            return null;
        }

        if (abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return null;
        }

        $signedContent = "{$id}.{$timestamp}.{$body}";
        $expected = array_map(
            fn (string $key) => base64_encode(hash_hmac('sha256', $signedContent, $key, true)),
            $this->signingKeys($secret),
        );

        $valid = false;
        foreach (explode(' ', $signatures) as $versioned) {
            [$version, $signature] = array_pad(explode(',', $versioned, 2), 2, '');

            if ($version !== 'v1') {
                continue;
            }

            foreach ($expected as $candidate) {
                if (hash_equals($candidate, $signature)) {
                    $valid = true;
                    break 2;
                }
            }
        }

        if (! $valid) {
            return null;
        }

        $event = json_decode($body, true);

        return is_array($event) ? $event : null;
    }

    /**
     * Polar signs with one of two keys depending on when the secret was made
     * (docs: "Handle & monitor webhook deliveries"):
     *  - on or after 8 Sep 2026 it is Standard Webhooks: the key is the
     *    base64 payload that follows `whsec_`;
     *  - older secrets are Polar HMAC: the key is the whole `whsec_…` string
     *    as UTF-8 bytes.
     * Polar's own SDKs try both, and so do we — a secret from before the
     * switch would otherwise fail every delivery, and Polar disables an
     * endpoint after ten consecutive failures.
     *
     * @return list<string>
     */
    protected function signingKeys(string $secret): array
    {
        $keys = [];

        if (str_starts_with($secret, 'whsec_')) {
            $decoded = base64_decode(substr($secret, 6), true);

            if ($decoded !== false && $decoded !== '') {
                $keys[] = $decoded;
            }
        }

        $keys[] = $secret;

        return $keys;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    protected function send(callable $call): array
    {
        $http = Http::baseUrl($this->baseUrl())
            ->withToken((string) config('billing.polar.access_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);

        try {
            $response = $call($http)->throw();
        } catch (RequestException $e) {
            $status = $e->response->status();

            // Polar's reason ("Product not found", a bad field) is what tells the
            // host owner what to fix. Only its words are logged, never our request.
            Log::warning('Polar API request failed', [
                'status' => $status,
                'reason' => $this->describeError($e->response),
            ]);

            throw new RuntimeException(
                $status >= 500 || $status === 429
                    ? "[billing_provider_error] Polar returned HTTP {$status}. Try again in a moment."
                    : "[billing_provider_error] Polar rejected the request (HTTP {$status}). This host's billing setup needs attention — do not retry; tell the person who runs this ReviseMy server.",
                previous: $e,
            );
        } catch (ConnectionException $e) {
            Log::warning('Polar API unreachable', ['error' => Str::limit($e->getMessage(), 200)]);

            throw new RuntimeException('[billing_provider_error] Could not reach Polar. Try again in a moment.', previous: $e);
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * Polar's error text without echoing back anything we sent (emails, URLs).
     */
    protected function describeError(Response $response): string
    {
        $json = $response->json();

        if (! is_array($json)) {
            return Str::limit(trim($response->body()), 200);
        }

        $detail = $json['detail'] ?? '';

        if (is_array($detail)) {
            $detail = collect($detail)
                ->map(fn ($item) => is_array($item)
                    ? trim(implode('.', (array) ($item['loc'] ?? [])).': '.($item['msg'] ?? ''), ': ')
                    : (string) $item)
                ->implode('; ');
        }

        return Str::limit(trim(($json['error'] ?? '').' '.$detail), 300);
    }
}
