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
