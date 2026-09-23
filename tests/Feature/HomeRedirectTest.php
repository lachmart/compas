<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('a guest opening the home page is sent to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('a logged-in dozor opening the home page lands on their games', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('admin.games.index'));
});

test('the dashboard route no longer exists', function () {
    expect(Route::has('dashboard'))->toBeFalse();
});
