<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isLocal = app()->environment('local');

        $scriptSrc = "'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com";
        $styleSrc = "'self' 'unsafe-inline' https://fonts.googleapis.com";
        $connectSrc = "'self'";
        $fontSrc = "'self' data: https://fonts.gstatic.com";

        if ($isLocal) {
            $scriptSrc .= " 'unsafe-eval' http://127.0.0.1:5173 http://localhost:5173";
            $styleSrc .= " http://127.0.0.1:5173 http://localhost:5173";
            $connectSrc .= " http://127.0.0.1:5173 http://localhost:5173 ws://127.0.0.1:5173 ws://localhost:5173";
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; ".
            "script-src {$scriptSrc}; ".
            "style-src {$styleSrc}; ".
            "img-src 'self' data: https://images.unsplash.com https://api.qrserver.com; ".
            "font-src {$fontSrc}; ".
            "connect-src {$connectSrc}; ".
            "frame-ancestors 'none'; ".
            "base-uri 'self'; ".
            "form-action 'self'"
        );

        return $response;
    }
}
