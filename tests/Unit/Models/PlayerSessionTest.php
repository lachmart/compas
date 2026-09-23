<?php

use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;

test('currentLocation returns the location at current_location_index', function () {
    $game = Game::factory()->create();
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 1]);

    expect($session->currentLocation()->id)->toBe($second->id);
});

test('currentLocation returns null past the last location', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 5]);

    expect($session->currentLocation())->toBeNull();
});

test('advance moves to the next location', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $session->advance();

    expect($session->fresh()->current_location_index)->toBe(1)
        ->and($session->fresh()->isFinished())->toBeFalse();
});

test('advance past the last location marks the session finished', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $session->advance();

    expect($session->fresh()->isFinished())->toBeTrue()
        ->and($session->fresh()->current_location_index)->toBe(0);
});

test('markSeen() stores the current time as last_seen_at', function () {
    $this->travelTo(now()->setTime(10, 15, 0));
    $session = PlayerSession::factory()->create(['last_seen_at' => null]);

    $session->markSeen();

    expect($session->fresh()->last_seen_at->format('H:i:s'))->toBe('10:15:00');
});

test('latestPing() returns the most recent ping', function () {
    $session = PlayerSession::factory()->create();
    $session->pings()->create(['lat' => 50.0, 'lng' => 14.0]);
    $session->pings()->create(['lat' => 50.1, 'lng' => 14.1]);

    expect((float) $session->latestPing->lat)->toBe(50.1);
});

test('color() is deterministic per player and a hex colour', function () {
    $session = PlayerSession::factory()->create();

    expect($session->color())->toMatch('/^#[0-9a-f]{6}$/')
        ->and($session->color())->toBe($session->fresh()->color());
});
