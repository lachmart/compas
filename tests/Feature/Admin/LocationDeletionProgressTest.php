<?php

use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;
use App\Models\User;

test('deleting a location shifts back players who were past it', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 3]);

    // Player is past the deleted (2nd) location, on the 3rd (index 2).
    $player = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 2]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $second]))
        ->assertRedirect();

    expect($player->fresh()->current_location_index)->toBe(1)
        ->and($player->fresh()->isFinished())->toBeFalse();
});

test('deleting a location leaves a player who was on it pointed at the next one', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 3]);

    // Player is on the location being deleted (index 1).
    $player = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 1]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $second]))
        ->assertRedirect();

    // Index unchanged, but it now points at what was the 3rd location.
    expect($player->fresh()->current_location_index)->toBe(1)
        ->and($player->fresh()->isFinished())->toBeFalse()
        ->and($player->fresh()->currentLocation()->order)->toBe(3);
});

test('deleting the last location finishes a player who was on it', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $last = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);

    $player = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 1]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $last]))
        ->assertRedirect();

    expect($player->fresh()->isFinished())->toBeTrue();
});

test('deleting the only location of a game finishes its unfinished players', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $only = Location::factory()->create(['game_id' => $game->id, 'order' => 1]);

    $player = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $only]))
        ->assertRedirect();

    expect($player->fresh()->isFinished())->toBeTrue();
});

test('deleting a location does not touch already-finished sessions', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 3]);

    $finishedAt = now()->subHour();
    $player = PlayerSession::factory()->create([
        'game_id' => $game->id,
        'current_location_index' => 2,
        'finished_at' => $finishedAt,
    ]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $second]))
        ->assertRedirect();

    $player->refresh();
    expect($player->current_location_index)->toBe(2)
        ->and($player->finished_at->format('Y-m-d H:i:s'))->toBe($finishedAt->format('Y-m-d H:i:s'));
});
