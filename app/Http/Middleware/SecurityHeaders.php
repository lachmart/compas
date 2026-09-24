<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // No framing by other sites (clickjacking).
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            // Other sites only ever learn the origin, never a full URL.
            // (Not no-referrer: OpenStreetMap tiles require a Referer.)
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // The game needs the camera, location and compass sensors from
            // this origin only; nothing else, and no embedded third party.
            'Permissions-Policy' => 'camera=(self), geolocation=(self), accelerometer=(self), gyroscope=(self), magnetometer=(self), microphone=(), payment=(), usb=()',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
