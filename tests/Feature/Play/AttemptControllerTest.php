<?php

use App\Enums\AttemptStatus;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function startSessionAt(Game $game, Location $current): array
{
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => $current->order - 1]);

    return [$session, function () use ($session) {
        return test()->withSession(['player_session_id' => $session->id]);
    }];
}

test('arriving at an arrival_only location completes it immediately and advances', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $loc->id]));

    $response->assertOk()->assertJson(['attempt_status' => 'completed']);
    expect($session->fresh()->current_location_index)->toBe(1);
});

test('arriving at a pick_image location unlocks the task without the correct answer', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id,
        'order' => 1,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2],
    ]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $loc->id]));

    $response->assertOk()
        ->assertJson(['attempt_status' => 'arrived', 'task_type' => 'pick_image'])
        ->assertJsonCount(3, 'images');
    $response->assertJsonMissingPath('correct_index');
    expect($session->fresh()->current_location_index)->toBe(0);
});

test('a correct answer completes the attempt and advances', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id,
        'order' => 1,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg'], 'correct_index' => 1],
    ]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $loc->id, 'status' => 'arrived',
    ]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.answer', [$game->slug, $loc->id]), ['selected_index' => 1]);

    $response->assertOk()->assertJson(['correct' => true]);
    expect($session->fresh()->current_location_index)->toBe(1);
});

test('a wrong answer does not advance and can be retried', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id,
        'order' => 1,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg'], 'correct_index' => 1],
    ]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $loc->id, 'status' => 'arrived',
    ]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.answer', [$game->slug, $loc->id]), ['selected_index' => 0]);

    $response->assertOk()->assertJson(['correct' => false]);
    expect($session->fresh()->current_location_index)->toBe(0);
});

test('submitting a photo completes a photo_of_something attempt', function () {
    Storage::fake('public');
    Storage::fake('local');
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id, 'order' => 1, 'task_type' => 'photo_of_something',
        'task_payload' => ['reference_image' => 'ref.jpg'],
    ]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $loc->id, 'status' => 'arrived',
    ]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->post(route('play.attempts.photo', [$game->slug, $loc->id]), [
            'photo' => UploadedFile::fake()->image('kids-photo.jpg'),
        ]);

    $response->assertOk();
    $attempt = LocationAttempt::first();
    expect($attempt->status)->toBe(AttemptStatus::Completed);
    // Children's photos are private and re-encoded, never public files.
    Storage::disk('local')->assertExists($attempt->submitted_photo_path);
    Storage::disk('public')->assertMissing($attempt->submitted_photo_path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('a player cannot act on a location that is not their current target', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $current = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    $future = Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $future->id]))
        ->assertForbidden();
});

test('a location belonging to a different game 404s even for a valid session', function () {
    $gameA = Game::factory()->create(['status' => GameStatus::Published]);
    $gameB = Game::factory()->create(['status' => GameStatus::Published]);
    $locB = Location::factory()->create(['game_id' => $gameB->id, 'order' => 1]);
    $sessionA = PlayerSession::factory()->create(['game_id' => $gameA->id, 'current_location_index' => 0]);

    $this->withSession(['player_session_id' => $sessionA->id])
        ->postJson(route('play.attempts.arrive', [$gameA->slug, $locB->id]))
        ->assertNotFound();
});

test('arriving 404s once the game has been unpublished mid-session', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $game->update(['status' => GameStatus::Draft]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $loc->id]))
        ->assertNotFound();
});
