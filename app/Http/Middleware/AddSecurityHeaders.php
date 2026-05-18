<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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

        $scriptSrc = "'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com";
        $styleSrc = "'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net";
        $connectSrc = "'self'";
        $fontSrc = "'self' data: https://fonts.gstatic.com https://fonts.bunny.net";

        if ($isLocal) {
            foreach ($this->viteDevOrigins() as $origin) {
                $scriptSrc .= " {$origin}";
                $styleSrc .= " {$origin}";
                $connectSrc .= " {$origin}";

                $websocketOrigin = preg_replace('/^http/i', 'ws', $origin);
                if (is_string($websocketOrigin)) {
                    $connectSrc .= " {$websocketOrigin}";
                }
            }
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $isEnotaryRoute = $this->isEnotaryRoute($request);

        if ($isEnotaryRoute) {
            $response->headers->set('Permissions-Policy', $this->enotaryPermissionsPolicy());
            $scriptSrc .= ' https://meet.jit.si https://8x8.vc https://*.onjitsi.com';
            $connectSrc .= ' https://meet.jit.si https://*.jit.si wss://*.jit.si wss://meet.jit.si wss://8x8.vc https://8x8.vc https://*.onjitsi.com wss://*.onjitsi.com';
        } else {
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }

        $frameSrc = $isEnotaryRoute
            ? "'self' https://meet.jit.si https://*.jit.si https://8x8.vc"
            : "'self'";

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; ".
            "script-src {$scriptSrc}; ".
            "style-src {$styleSrc}; ".
            "img-src 'self' data: https://images.unsplash.com https://api.qrserver.com; ".
            "font-src {$fontSrc}; ".
            "connect-src {$connectSrc}; ".
            "frame-src {$frameSrc}; ".
            "frame-ancestors 'none'; ".
            "base-uri 'self'; ".
            "form-action 'self'"
        );

        return $response;
    }

    private function isEnotaryRoute(Request $request): bool
    {
        return $request->routeIs([
            'notary-requests.show',
            'notary.requests.show',
            'notary-requests.session.live',
            'notary.requests.session.live',
        ]);
    }

    private function enotaryPermissionsPolicy(): string
    {
        return 'camera=(self "https://8x8.vc" "https://meet.jit.si"), microphone=(self "https://8x8.vc" "https://meet.jit.si"), display-capture=(self "https://8x8.vc" "https://meet.jit.si"), geolocation=(self)';
    }

    /**
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        $origins = [
            'http://127.0.0.1:5173',
            'http://localhost:5173',
        ];

        $hotFile = public_path('hot');
        if (File::exists($hotFile)) {
            $hotUrl = trim((string) File::get($hotFile));

            if ($hotUrl !== '') {
                $origins[] = rtrim($hotUrl, '/');
            }
        }

        return array_values(array_unique($origins));
    }
}
