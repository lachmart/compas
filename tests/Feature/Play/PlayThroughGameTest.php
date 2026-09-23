<?php

use App\Enums\GameStatus;
use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a player can complete a full game spanning every task type', function () {
    Storage::fake('public');

    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $arrival = Location::factory()->create([
        'game_id' => $game->id, 'order' => 1, 'task_type' => TaskType::ArrivalOnly,
    ]);
    $pickImage = Location::factory()->create([
        'game_id' => $game->id, 'order' => 2, 'task_type' => TaskType::PickImage,
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2],
    ]);
    $photo = Location::factory()->create([
        'game_id' => $game->id, 'order' => 3, 'task_type' => TaskType::PhotoOfSomething,
        'task_payload' => ['reference_image' => 'ref.jpg'],
    ]);
    $selfie = Location::factory()->create([
        'game_id' => $game->id, 'order' => 4, 'task_type' => TaskType::SelfieDoingSomething,
        'task_payload' => ['reference_image' => 'ref2.jpg'],
    ]);

    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    $asPlayer = fn () => test()->withSession(['player_session_id' => $session->id]);

    // Location 1 (arrival_only): arriving alone completes it and advances.
    $asPlayer()->postJson(route('play.attempts.arrive', [$game->slug, $arrival->id]))
        ->assertOk()
        ->assertJson(['attempt_status' => 'completed']);
    expect($session->fresh()->current_location_index)->toBe(1);

    // Location 2 (pick_image): arriving unlocks the task, the correct answer advances.
    $asPlayer()->postJson(route('play.attempts.arrive', [$game->slug, $pickImage->id]))
        ->assertOk()
        ->assertJson(['attempt_status' => 'arrived', 'task_type' => 'pick_image']);
    expect($session->fresh()->current_location_index)->toBe(1);

    $asPlayer()->postJson(route('play.attempts.answer', [$game->slug, $pickImage->id]), ['selected_index' => 2])
        ->assertOk()
        ->assertJson(['correct' => true]);
    expect($session->fresh()->current_location_index)->toBe(2);

    // Location 3 (photo_of_something): arriving unlocks the task, an uploaded photo advances.
    $asPlayer()->postJson(route('play.attempts.arrive', [$game->slug, $photo->id]))
        ->assertOk()
        ->assertJson(['attempt_status' => 'arrived', 'task_type' => 'photo_of_something']);

    $asPlayer()->post(route('play.attempts.photo', [$game->slug, $photo->id]), [
        'photo' => UploadedFile::fake()->image('photo-of-something.jpg'),
    ])->assertOk();
    expect($session->fresh()->current_location_index)->toBe(3);

    // Location 4 (selfie_doing_something): the final location, completing it finishes the game.
    $asPlayer()->postJson(route('play.attempts.arrive', [$game->slug, $selfie->id]))
        ->assertOk()
        ->assertJson(['attempt_status' => 'arrived', 'task_type' => 'selfie_doing_something']);

    $final = $asPlayer()->post(route('play.attempts.photo', [$game->slug, $selfie->id]), [
        'photo' => UploadedFile::fake()->image('selfie.jpg'),
    ]);

    $final->assertOk();
    expect($final->json('player.finished_at'))->not->toBeNull();
    expect($session->fresh()->finished_at)->not->toBeNull();
});
