<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\PlayerSession;
use App\Models\PositionPing;

test('a player can record a position ping', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $game->slug), [
            'lat' => 50.08,
            'lng' => 14.43,
            'heading' => 180.5,
        ]);

    $response->assertNoContent();
    expect(PositionPing::where('player_session_id', $session->id)->count())->toBe(1);
});

test('a ping without a valid session is rejected', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->postJson(route('play.pings.store', $game->slug), ['lat' => 50.08, 'lng' => 14.43])
        ->assertForbidden();
});

test('a ping requires valid coordinates', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $game->slug), ['lat' => 999, 'lng' => 14.43])
        ->assertJsonValidationErrors('lat');
});

test('a ping 404s once the game has been unpublished', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $game->update(['status' => GameStatus::Draft]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $game->slug), ['lat' => 50.08, 'lng' => 14.43])
        ->assertNotFound();
});
