<?php

namespace App\Services\Security;

use Illuminate\Foundation\Http\Events\RequestHandled;

/** Apply headers to the final rendered response, including exception responses. */
class ApiResponseSecurityHeaders
{
    public function handle(RequestHandled $event): void
    {
        if (! $event->request->is('api/*')) {
            return;
        }

        $headers = $event->response->headers;
        $headers->set('Cache-Control', 'no-store, private');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}
