<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=self, microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.datatables.net https://cdn.jsdelivr.net; " .
            "font-src 'self' https://fonts.gstatic.com data:; " .
            "img-src 'self' data: blob:; " .
            "media-src 'self' blob:; " .
            "connect-src 'self' blob:; " .
            "worker-src 'self' blob:;"
        );

        // Only send HSTS header over HTTPS
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Remove server fingerprint headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
