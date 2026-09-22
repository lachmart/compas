<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;

test('location casts task_type to enum and task_payload to array', function () {
    $location = Location::factory()->create([
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg'], 'correct_index' => 0],
    ]);

    expect($location->task_type)->toBe(TaskType::PickImage)
        ->and($location->task_payload)->toBeArray();
});

test('locations belong to a game ordered by order column', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);

    expect($game->locations->pluck('order')->all())->toBe([1, 2]);
});
