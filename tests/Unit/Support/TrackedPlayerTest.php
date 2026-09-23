<?php

use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;
use App\Support\TrackedPlayer;

test('make() describes a player with their latest position', function () {
    $game = Game::factory()->create();
    Location::factory()->count(3)->sequence(fn ($s) => ['order' => $s->index + 1])->create(['game_id' => $game->id]);
    $session = PlayerSession::factory()->create([
        'game_id' => $game->id,
        'nickname' => 'Anna',
        'current_location_index' => 1,
        'last_seen_at' => '2026-09-23 10:00:00',
    ]);
    $session->pings()->create(['lat' => 50.08, 'lng' => 14.43, 'heading' => 90]);

    $data = TrackedPlayer::make($session);

    expect($data)->toMatchArray([
        'id' => $session->id,
        'nickname' => 'Anna',
        'avatar' => 'fox',
        'color' => $session->color(),
        'current_location_index' => 1,
        'locations_count' => 3,
        'finished_at' => null,
        'position' => ['lat' => 50.08, 'lng' => 14.43, 'heading' => 90.0],
    ])->and($data['last_seen_at'])->toStartWith('2026-09-23T10:00:00');
});

test('make() has a null position and falls back to started_at when never seen', function () {
    $session = PlayerSession::factory()->create([
        'started_at' => '2026-09-23 09:00:00',
        'last_seen_at' => null,
    ]);

    $data = TrackedPlayer::make($session);

    expect($data['position'])->toBeNull()
        ->and($data['last_seen_at'])->toStartWith('2026-09-23T09:00:00');
});
