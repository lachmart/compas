<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->user->id]);
    foreach (['a', 'b', 'c'] as $name) {
        Storage::disk('public')->put("task-images/{$name}.jpg", 'x');
    }
    $this->location = Location::factory()->create([
        'game_id' => $this->game->id,
        'task_type' => TaskType::PickImage,
        'task_payload' => ['images' => ['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg'], 'correct_index' => 0],
    ]);
    $this->base = ['lat' => 50.08, 'lng' => 14.43, 'arrival_radius_m' => 15];
});

function updateLocation($test, array $data)
{
    return $test->actingAs($test->user)
        ->put(route('admin.games.locations.update', [$test->game, $test->location]), $test->base + $data);
}

test('keeping all existing images without uploading anything preserves them', function () {
    updateLocation($this, [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg'],
        'correct_index' => 1,
    ])->assertSessionHasNoErrors();

    $payload = $this->location->fresh()->task_payload;
    expect($payload['images'])->toBe(['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg'])
        ->and($payload['correct_index'])->toBe(1);
    Storage::disk('public')->assertExists(['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg']);
});

test('removing one image and adding one keeps order and deletes the removed file', function () {
    updateLocation($this, [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg', 'task-images/c.jpg'],
        'images' => [UploadedFile::fake()->image('new.jpg')],
        'correct_index' => 2,
    ])->assertSessionHasNoErrors();

    $payload = $this->location->fresh()->task_payload;
    expect($payload['images'])->toHaveCount(3)
        ->and(array_slice($payload['images'], 0, 2))->toBe(['task-images/a.jpg', 'task-images/c.jpg'])
        ->and($payload['correct_index'])->toBe(2);
    Storage::disk('public')->assertExists($payload['images'][2]);
    Storage::disk('public')->assertMissing('task-images/b.jpg');
});

test('existing_images may not reference another location\'s image', function () {
    Storage::disk('public')->put('task-images/foreign.jpg', 'x');
    Location::factory()->create([
        'game_id' => $this->game->id,
        'task_type' => TaskType::PickImage,
        'task_payload' => ['images' => ['task-images/foreign.jpg'], 'correct_index' => 0],
    ]);

    updateLocation($this, [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg', 'task-images/b.jpg', 'task-images/foreign.jpg'],
        'correct_index' => 0,
    ])->assertSessionHasErrors('existing_images.2');

    Storage::disk('public')->assertExists('task-images/foreign.jpg');
});

test('a new location cannot claim existing images', function () {
    $this->actingAs($this->user)->post(route('admin.games.locations.store', $this->game), $this->base + [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg'],
        'images' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')],
        'correct_index' => 0,
    ])->assertSessionHasErrors('existing_images.0');
});

test('fewer than three images in total is rejected', function () {
    updateLocation($this, [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg', 'task-images/b.jpg'],
        'correct_index' => 0,
    ])->assertSessionHasErrors('images');
});

test('more than six images in total is rejected', function () {
    updateLocation($this, [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg'],
        'images' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 4)),
        'correct_index' => 0,
    ])->assertSessionHasErrors('images');
});

test('correct_index must point inside the combined list', function () {
    updateLocation($this, [
        'task_type' => 'pick_image',
        'existing_images' => ['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg'],
        'correct_index' => 3,
    ])->assertSessionHasErrors('correct_index');
});

test('a photo task keeps its reference image when no new file is sent', function () {
    Storage::disk('public')->put('task-images/ref.jpg', 'x');
    $this->location->update(['task_type' => TaskType::PhotoOfSomething, 'task_payload' => ['reference_image' => 'task-images/ref.jpg']]);

    updateLocation($this, ['task_type' => 'photo_of_something'])->assertSessionHasNoErrors();

    expect($this->location->fresh()->task_payload['reference_image'])->toBe('task-images/ref.jpg');
    Storage::disk('public')->assertExists('task-images/ref.jpg');
});

test('a new reference image replaces the old one and deletes it', function () {
    Storage::disk('public')->put('task-images/ref.jpg', 'x');
    $this->location->update(['task_type' => TaskType::PhotoOfSomething, 'task_payload' => ['reference_image' => 'task-images/ref.jpg']]);

    updateLocation($this, [
        'task_type' => 'selfie_doing_something',
        'reference_image' => UploadedFile::fake()->image('new-ref.jpg'),
    ])->assertSessionHasNoErrors();

    $reference = $this->location->fresh()->task_payload['reference_image'];
    expect($reference)->not->toBe('task-images/ref.jpg');
    Storage::disk('public')->assertExists($reference);
    Storage::disk('public')->assertMissing('task-images/ref.jpg');
});

test('switching to a photo task without a reference image is rejected', function () {
    updateLocation($this, ['task_type' => 'photo_of_something'])->assertSessionHasErrors('reference_image');
});

test('switching away from pick_image deletes its images', function () {
    updateLocation($this, ['task_type' => 'arrival_only'])->assertSessionHasNoErrors();

    expect($this->location->fresh()->task_payload)->toBeNull();
    Storage::disk('public')->assertMissing(['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg']);
});

test('deleting a location deletes its files', function () {
    $this->actingAs($this->user)
        ->delete(route('admin.games.locations.destroy', [$this->game, $this->location]))
        ->assertRedirect();

    Storage::disk('public')->assertMissing(['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg']);
});
