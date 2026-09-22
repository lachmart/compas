<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;

test('guests cannot access the games dashboard', function () {
    $this->get(route('admin.games.index'))->assertRedirect(route('login'));
});

test('a dozor can create a game and gets a unique slug', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.games.store'), [
        'name' => 'Lesní dobrodružství',
    ]);

    $game = Game::first();

    $response->assertRedirect(route('admin.games.edit', $game));
    expect($game->owner_id)->toBe($user->id)
        ->and($game->slug)->toBe('lesni-dobrodruzstvi')
        ->and($game->status)->toBe(GameStatus::Draft);
});

test('a dozor only sees their own games', function () {
    // This is the only test in the suite that renders a full Inertia page
    // via a real GET request. The Admin/Games/Index Vue component doesn't
    // exist yet (it lands in a later, frontend-focused task), so the
    // built Vite manifest has no entry for it and inertia-laravel's
    // testing helper would otherwise fail the on-disk component check.
    // Scoped to this test only so the rest of the suite keeps the real
    // Vite-manifest/component-existence checks that catch typos in
    // Inertia::render component names.
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Game::factory()->create(['owner_id' => $owner->id]);
    Game::factory()->create(['owner_id' => $stranger->id]);

    $this->actingAs($owner)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Games/Index')->has('games', 1));
});

test("a dozor cannot edit another dozor's game", function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($stranger)->get(route('admin.games.edit', $game))->assertForbidden();
    $this->actingAs($stranger)->put(route('admin.games.update', $game), [
        'name' => 'x', 'slug' => 'x',
    ])->assertForbidden();
});

test('a dozor can update the game name and slug', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->put(route('admin.games.update', $game), [
        'name' => 'Nový název',
        'slug' => 'novy-nazev',
    ])->assertRedirect();

    expect($game->fresh()->name)->toBe('Nový název')
        ->and($game->fresh()->slug)->toBe('novy-nazev');
});

test('a dozor can publish and unpublish a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id, 'status' => GameStatus::Draft]);

    $this->actingAs($user)->patch(route('admin.games.publish', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Published);

    $this->actingAs($user)->patch(route('admin.games.unpublish', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Draft);
});

test('a dozor can delete their own game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->delete(route('admin.games.destroy', $game))
        ->assertRedirect(route('admin.games.index'));

    expect(Game::find($game->id))->toBeNull();
});
