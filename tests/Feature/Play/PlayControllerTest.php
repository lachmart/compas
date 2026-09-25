<?php

use App\Enums\Avatar;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;

test('visiting an unpublished game 404s', function () {
    $game = Game::factory()->create(['status' => GameStatus::Draft]);

    $this->get(route('play.show', $game->slug))->assertNotFound();
});

test('visiting a published game without a session shows the join page', function () {
    // This is one of the tests in the suite that renders a full Inertia page
    // via a real GET request. The Play/Join Vue component doesn't exist yet
    // (it lands in a later, frontend-focused task), so the built Vite
    // manifest has no entry for it and inertia-laravel's testing helper
    // would otherwise fail the on-disk component check. Scoped to this test
    // only so the rest of the suite keeps the real Vite-manifest/component-
    // existence checks that catch typos in Inertia::render component names.
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Join')
            ->where('game.slug', $game->slug));
});

test('a child can join a published game with just a nickname', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);

    $response = $this->post(route('play.join', $game->slug), ['avatar' => 'fox', 'nickname' => 'Anežka']);

    $response->assertRedirect(route('play.show', $game->slug));
    $session = PlayerSession::first();
    expect($session->nickname)->toBe('Anežka')
        ->and($session->game_id)->toBe($game->id)
        ->and($session->current_location_index)->toBe(0);
});

test('a player joins by picking an animal, the name is optional', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'owl'])->assertRedirect();

    $session = PlayerSession::first();
    expect($session->avatar)->toBe(Avatar::Owl)
        ->and($session->nickname)->toBe('Sova');
});

test('an adult-typed name is kept alongside the animal', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'fox', 'nickname' => '  Anička '])->assertRedirect();

    expect(PlayerSession::first()->nickname)->toBe('Anička');
});

test('joining requires a known animal', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'dragon'])->assertSessionHasErrors('avatar');
    $this->post(route('play.join', $game->slug), [])->assertSessionHasErrors('avatar');
});

test('the play screen receives the player avatar', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'avatar' => 'bear']);

    $this->withSession(['player_session_id' => $session->id])->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->where('player.avatar', 'bear'));
});

test('visiting with an active session shows the play screen with game state', function () {
    // See comment in the "shows the join page" test above: this test also
    // renders a full Inertia page (Play/Show) via a real GET request, and
    // that Vue component doesn't exist yet either.
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Show')
            ->has('locations', 2)
            ->where('player.current_location_index', 0)
            ->where('locations.0.id', $first->id));
});

test('the play screen never exposes location names to the player', function () {
    // See comment in the "shows the join page" test above: this test also
    // renders a full Inertia page (Play/Show) via a real GET request.
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'name' => 'Tajná studánka']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Show')
            ->missing('locations.0.name'));
});

test("a session for a different game does not leak into this game's play screen", function () {
    // See comment in the "shows the join page" test above: this test also
    // renders a full Inertia page (Play/Join) via a real GET request.
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $gameA = Game::factory()->create(['status' => GameStatus::Published]);
    $gameB = Game::factory()->create(['status' => GameStatus::Published]);
    $sessionForA = PlayerSession::factory()->create(['game_id' => $gameA->id]);

    $this->withSession(['player_session_id' => $sessionForA->id])
        ->get(route('play.show', $gameB->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Join'));
});
