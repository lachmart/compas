<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->user->id]);
});

function previewStation(Game $game, int $order, array $attributes = []): Location
{
    return Location::factory()->create(['game_id' => $game->id, 'order' => $order, ...$attributes]);
}

test('the dozor sees a station as the player would, with its neighbours', function () {
    $first = previewStation($this->game, 1);
    $second = previewStation($this->game, 2, [
        'task_type' => TaskType::PickImage,
        'task_payload' => ['images' => ['task-images/a.jpg', 'task-images/b.jpg'], 'correct_index' => 1],
        'info_title' => 'Starý dub',
    ]);
    $third = previewStation($this->game, 3);

    $this->actingAs($this->user)
        ->get(route('admin.games.locations.preview', [$this->game, $second]))
        ->assertInertia(fn ($page) => $page->component('Admin/Games/StationPreview')
            ->where('position', 2)
            ->where('total', 3)
            ->where('previous_id', $first->id)
            ->where('next_id', $third->id)
            ->where('location.task_type', 'pick_image')
            ->where('location.correct_index', 1)
            ->where('location.info.title', 'Starý dub')
            ->has('location.images', 2));
});

test('the first and last stations have no neighbour on that side', function () {
    $only = previewStation($this->game, 1);

    $this->actingAs($this->user)
        ->get(route('admin.games.locations.preview', [$this->game, $only]))
        ->assertInertia(fn ($page) => $page->where('previous_id', null)->where('next_id', null));
});

test('a password is checked like in the game without recording anything', function () {
    $station = previewStation($this->game, 1, [
        'task_type' => TaskType::Password,
        'task_payload' => ['answers' => ['Žába']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('admin.games.locations.preview.password', [$this->game, $station]), ['answer' => ' ZABA '])
        ->assertOk()->assertJson(['correct' => true]);

    $this->actingAs($this->user)
        ->postJson(route('admin.games.locations.preview.password', [$this->game, $station]), ['answer' => 'kos'])
        ->assertJson(['correct' => false]);

    expect(LocationAttempt::count())->toBe(0);
});

test('only the game owner can preview its stations', function () {
    $station = previewStation($this->game, 1);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('admin.games.locations.preview', [$this->game, $station]))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->postJson(route('admin.games.locations.preview.password', [$this->game, $station]), ['answer' => 'x'])
        ->assertForbidden();
});

test('a station of another game is not found', function () {
    $foreign = previewStation(Game::factory()->create(['owner_id' => $this->user->id]), 1);

    $this->actingAs($this->user)
        ->get(route('admin.games.locations.preview', [$this->game, $foreign]))
        ->assertNotFound();
});
