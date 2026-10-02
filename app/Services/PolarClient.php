<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    /**
     * @return array<string, mixed>
     */
    public function getCheckout(string $checkoutId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('/v1/checkouts/'.rawurlencode($checkoutId)));
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

        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $this->signingKey($secret), true));

        $valid = false;
        foreach (explode(' ', $signatures) as $versioned) {
            [$version, $signature] = array_pad(explode(',', $versioned, 2), 2, '');

            if ($version === 'v1' && hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            return null;
        }

        $event = json_decode($body, true);

        return is_array($event) ? $event : null;
    }

    /**
     * Standard Webhooks secrets are `whsec_<base64 key>`. Polar secrets issued
     * before Sept 2026 are a raw string that Standard Webhooks libraries take
     * base64-encoded — i.e. the raw bytes are the key.
     */
    protected function signingKey(string $secret): string
    {
        if (str_starts_with($secret, 'whsec_')) {
            $decoded = base64_decode(substr($secret, 6), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $secret;
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
            throw new RuntimeException(
                '[billing_provider_error] Polar returned HTTP '.$e->response->status().'. Try again in a moment.',
                previous: $e,
            );
        } catch (ConnectionException $e) {
            throw new RuntimeException('[billing_provider_error] Could not reach Polar. Try again in a moment.', previous: $e);
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
