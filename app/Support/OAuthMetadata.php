<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What an assistant reads to find out how to sign in to ReviseMy: the
 * protected-resource document (RFC 9728), the authorization server document
 * (RFC 8414), and the 401 challenge that points at them.
 *
 * Laravel MCP serves leaner versions. These add what Claude's connector docs
 * and the MCP spec ask for: the auth method a public client uses, how to
 * send the token, which scope to ask for, and `invalid_token` on a token we
 * turned down, so the host refreshes instead of starting over.
 */
class OAuthMetadata
{
    public const SCOPE = 'mcp:use';

    /** The MCP addresses this app serves. The -grok one exists for Grok's cache. */
    public const MCP_PATHS = ['mcp/revisemy', 'mcp/revisemy-grok'];

    /**
     * @return array<string, mixed>
     */
    public static function protectedResource(string $path): array
    {
        return [
            'resource' => url('/'.ltrim($path, '/')),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => [self::SCOPE],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'ReviseMy',
            'resource_documentation' => url('/docs/mcp'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function authorizationServer(): array
    {
        return [
            'issuer' => self::issuer(),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => url('/oauth/register'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            // Registration makes public clients: no secret, PKCE instead.
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [self::SCOPE],
            'service_documentation' => url('/docs/authentication'),
        ];
    }

    /**
     * Discovery documents change only on deploy (which purges the edge), so
     * a host that connects twice in a row needn't fetch them twice.
     *
     * @param  array<string, mixed>  $document
     */
    public static function json(array $document): JsonResponse
    {
        return response()->json($document)->header('Cache-Control', 'public, max-age=300');
    }

    /** The WWW-Authenticate value for a 401 on an MCP address. */
    public static function challenge(Request $request, bool $rejected = false): string
    {
        $parts = [
            'realm="mcp"',
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/'.$request->path()).'"',
            'scope="'.self::SCOPE.'"',
        ];

        if ($rejected) {
            $parts[] = 'error="invalid_token"';
            $parts[] = 'error_description="The access token is expired, revoked or not ours"';
        }

        return 'Bearer '.implode(', ', $parts);
    }

    private static function issuer(): string
    {
        return config('mcp.authorization_server') ?? url('/');
    }
}
