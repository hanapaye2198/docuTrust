<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds Permissions-Policy header to allow camera/microphone in iframes.
 * Required for Jitsi video conferencing embedded via iframe.
 */
class AllowMediaPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'Permissions-Policy',
            'camera=(self "https://8x8.vc" "https://meet.jit.si"), microphone=(self "https://8x8.vc" "https://meet.jit.si"), display-capture=(self "https://8x8.vc" "https://meet.jit.si")'
        );

        return $response;
    }
}
