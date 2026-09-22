<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;

test('game casts status to enum', function () {
    $game = Game::factory()->create(['status' => 'published']);

    expect($game->status)->toBe(GameStatus::Published);
});

test('game belongs to an owner', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    expect($game->owner->id)->toBe($user->id);
});

test('user has many games', function () {
    $user = User::factory()->create();
    Game::factory()->count(2)->create(['owner_id' => $user->id]);

    expect($user->games)->toHaveCount(2);
});
