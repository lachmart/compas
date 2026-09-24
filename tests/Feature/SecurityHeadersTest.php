<?php

test('pages are sent with security headers', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('geolocation=(self)')->toContain('camera=(self)')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('HSTS is sent over HTTPS', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
