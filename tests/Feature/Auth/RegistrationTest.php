<?php

use App\Models\User;

describe('when registration is enabled', function () {
    beforeEach(fn () => config(['app.registration_enabled' => true]));

    test('registration screen can be rendered', function () {
        $response = $this->get('/register');

        $response->assertStatus(200);
    });

    test('new users can register', function () {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.games.index', absolute: false));
    });

    test('the login page offers sign-up', function () {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', true));
    });
});

describe('when registration is disabled', function () {
    beforeEach(fn () => config(['app.registration_enabled' => false]));

    test('the registration screen is not found', function () {
        $this->get('/register')->assertNotFound();
    });

    test('nobody can register', function () {
        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        expect(User::where('email', 'intruder@example.com')->exists())->toBeFalse();
    });

    test('the login page hides sign-up', function () {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', false));
    });
});
