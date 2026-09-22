<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a dozor can add an arrival_only location', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'arrival_only',
    ])->assertRedirect();

    $location = $game->locations()->first();
    expect($location->task_type)->toBe(TaskType::ArrivalOnly)
        ->and($location->task_payload)->toBeNull()
        ->and($location->order)->toBe(1);
});

test('a dozor can add a pick_image location with uploaded images', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'pick_image',
        'images' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ],
        'correct_index' => 1,
    ])->assertRedirect();

    $location = $game->locations()->first();
    expect($location->task_payload['correct_index'])->toBe(1)
        ->and($location->task_payload['images'])->toHaveCount(3);
    Storage::disk('public')->assertExists($location->task_payload['images'][0]);
});

test('pick_image requires at least three images', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'pick_image',
        'images' => [UploadedFile::fake()->image('a.jpg')],
        'correct_index' => 0,
    ])->assertSessionHasErrors('images');
});

test('a dozor can add a photo_of_something location with a reference image', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'photo_of_something',
        'reference_image' => UploadedFile::fake()->image('ref.jpg'),
    ])->assertRedirect();

    $location = $game->locations()->first();
    Storage::disk('public')->assertExists($location->task_payload['reference_image']);
});

test('a dozor can reorder locations', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);

    $this->actingAs($user)->patch(route('admin.games.locations.reorder', $game), [
        'order' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->order)->toBe(1)
        ->and($first->fresh()->order)->toBe(2);
});

test("a dozor cannot modify locations of another dozor's game", function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($stranger)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'arrival_only',
    ])->assertForbidden();
});

test('a dozor can delete a location', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create(['game_id' => $game->id]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $location]))
        ->assertRedirect();

    expect(Location::find($location->id))->toBeNull();
});
