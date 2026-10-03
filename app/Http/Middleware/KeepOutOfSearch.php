<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep a secret link out of search results, in the response itself.
 *
 * A review link is the whole of its access control, and a page robots.txt
 * blocks can still be listed from somebody's link; this header is what keeps
 * it out. On every review, board, screenshot and billing response, so a page
 * that forgets its meta tag, or an image that has none, is still covered.
 * Adapted from Koati's, which puts it on everything until launch.
 */
class KeepOutOfSearch
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
