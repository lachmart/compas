<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;

test('joining a game marks the player as seen', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'fox', 'nickname' => 'Anna']);

    expect(PlayerSession::first()->last_seen_at)->not->toBeNull();
});

test('any player request refreshes last_seen_at', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 0]]);
    $session = PlayerSession::factory()->create([
        'game_id' => $game->id,
        'last_seen_at' => now()->subHour(),
    ]);

    $this->travelTo(now()->addMinute());
    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $game->locations()->first()->id]))
        ->assertOk();

    expect($session->fresh()->last_seen_at->diffInSeconds(now()))->toBeLessThan(2);
});
