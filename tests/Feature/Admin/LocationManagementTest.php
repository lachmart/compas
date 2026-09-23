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

test('pick_image rejects a correct_index outside the uploaded images', function () {
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
        'correct_index' => 3,
    ])->assertSessionHasErrors('correct_index');

    expect(Location::count())->toBe(0);
});

test('forgetting to pick a correct image shows a Czech validation message', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'pick_image',
        'images' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ],
    ]);

    $response->assertSessionHasErrors('correct_index');
    expect(session('errors')->get('correct_index'))->toBe(['Označ správný obrázek.']);
});

test('pick_image accepts a correct_index within range of the uploaded images', function () {
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
        'correct_index' => 2,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $location = $game->locations()->first();
    expect($location->task_payload['correct_index'])->toBe(2);
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

test('reordering rejects a partial order array missing one of the game locations', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2]);

    $this->actingAs($user)->patch(route('admin.games.locations.reorder', $game), [
        'order' => [$first->id],
    ])->assertStatus(422);
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

test('a dozor can update an arrival_only location', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create([
        'game_id' => $game->id,
        'task_type' => TaskType::ArrivalOnly,
        'arrival_radius_m' => 15,
    ]);

    $this->actingAs($user)->put(route('admin.games.locations.update', [$game, $location]), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 42,
        'task_type' => 'arrival_only',
    ])->assertRedirect();

    expect($location->fresh()->arrival_radius_m)->toBe(42)
        ->and($location->fresh()->task_payload)->toBeNull();
});

test('a dozor can update a pick_image location by re-uploading images', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create([
        'game_id' => $game->id,
        'task_type' => TaskType::PickImage,
        'task_payload' => ['images' => ['old-a.jpg', 'old-b.jpg', 'old-c.jpg'], 'correct_index' => 0],
    ]);

    $this->actingAs($user)->put(route('admin.games.locations.update', [$game, $location]), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'pick_image',
        'images' => [
            UploadedFile::fake()->image('new-a.jpg'),
            UploadedFile::fake()->image('new-b.jpg'),
            UploadedFile::fake()->image('new-c.jpg'),
        ],
        'correct_index' => 2,
    ])->assertRedirect();

    $location->refresh();
    expect($location->task_payload['correct_index'])->toBe(2)
        ->and($location->task_payload['images'])->toHaveCount(3);
    Storage::disk('public')->assertExists($location->task_payload['images'][0]);
});

test('a dozor can update a photo_of_something location by re-uploading the reference image', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create([
        'game_id' => $game->id,
        'task_type' => TaskType::PhotoOfSomething,
        'task_payload' => ['reference_image' => 'old-ref.jpg'],
    ]);

    $this->actingAs($user)->put(route('admin.games.locations.update', [$game, $location]), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'photo_of_something',
        'reference_image' => UploadedFile::fake()->image('new-ref.jpg'),
    ])->assertRedirect();

    $location->refresh();
    Storage::disk('public')->assertExists($location->task_payload['reference_image']);
});

test('a dozor cannot update or delete a location belonging to another game, even one they own', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $gameA = Game::factory()->create(['owner_id' => $ownerA->id]);
    $gameB = Game::factory()->create(['owner_id' => $ownerB->id]);
    $locationB = Location::factory()->create(['game_id' => $gameB->id]);

    $this->actingAs($ownerA)->put(route('admin.games.locations.update', [$gameA, $locationB]), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'arrival_only',
    ])->assertNotFound();

    $this->actingAs($ownerA)->delete(route('admin.games.locations.destroy', [$gameA, $locationB]))
        ->assertNotFound();

    expect(Location::find($locationB->id))->not->toBeNull();
});

test('a dozor can delete a location', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create(['game_id' => $game->id]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $location]))
        ->assertRedirect();

    expect(Location::find($location->id))->toBeNull();
});

test('a location can be stored and updated with an optional name', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $base = ['lat' => 50.08, 'lng' => 14.43, 'arrival_radius_m' => 15, 'task_type' => 'arrival_only'];

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), $base + ['name' => 'Studánka'])
        ->assertSessionHasNoErrors();
    $location = $game->locations()->first();
    expect($location->name)->toBe('Studánka');

    $this->actingAs($user)->put(route('admin.games.locations.update', [$game, $location]), $base + ['name' => ''])
        ->assertSessionHasNoErrors();
    expect($location->fresh()->name)->toBeNull();
});

test('a location name longer than 100 characters is rejected', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08, 'lng' => 14.43, 'arrival_radius_m' => 15, 'task_type' => 'arrival_only',
        'name' => str_repeat('a', 101),
    ])->assertSessionHasErrors('name');
});
