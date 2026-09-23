<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\PlayerSession;

// Global Constraints (plan): a Reverb outage must never fail a player
// request. `QUEUE_CONNECTION=sync` in phpunit.xml alone doesn't prove that —
// it just means the broadcast job runs inline. Here we point the reverb
// connection at an unreachable host/port so the underlying Pusher/Guzzle
// call actually throws, and assert the player-facing response still
// succeeds.
beforeEach(function () {
    config()->set([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'unreachable-key',
        'broadcasting.connections.reverb.secret' => 'unreachable-secret',
        'broadcasting.connections.reverb.app_id' => 'unreachable-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
        'queue.default' => 'sync',
    ]);
});

test('a ping still succeeds when the broadcaster is unreachable', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $game->slug), ['lat' => 50.08, 'lng' => 14.43])
        ->assertNoContent();
});

test('arriving at an arrival_only location still completes when the broadcaster is unreachable', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = addLocation($game, 1);
    addLocation($game, 2);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $loc->id]));

    $response->assertOk()->assertJson(['attempt_status' => 'completed']);
    expect($session->fresh()->current_location_index)->toBe(1);
});

test('joining still succeeds when the broadcaster is unreachable', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    addLocation($game, 1);

    $this->post(route('play.join', $game->slug), ['avatar' => 'fox', 'nickname' => 'Anna'])
        ->assertRedirect(route('play.show', $game->slug));
});
