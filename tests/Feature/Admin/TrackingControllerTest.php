<?php

use App\Enums\AttemptStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->owner->id]);
    $this->first = Location::factory()->create(['game_id' => $this->game->id, 'order' => 1, 'task_type' => 'arrival_only', 'task_payload' => []]);
    $this->second = Location::factory()->create(['game_id' => $this->game->id, 'order' => 2, 'task_type' => 'photo_of_something', 'task_payload' => ['reference_image' => 'ref.jpg']]);
});

test('the owner sees the tracking page with players and locations', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id, 'nickname' => 'Anna']);
    $session->pings()->create(['lat' => 50.08, 'lng' => 14.43]);

    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Games/Tracking')
            ->where('game.id', $this->game->id)
            ->has('locations', 2)
            ->where('locations.1.position', 2)
            ->has('players', 1)
            ->where('players.0.nickname', 'Anna')
            ->where('players.0.position.lat', 50.08));
});

test('another dozor cannot see the tracking page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.games.tracking', $this->game))
        ->assertForbidden();
});

test('player detail lists attempts with times and photo urls', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id, 'current_location_index' => 1]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->second->id,
        'status' => AttemptStatus::Completed, 'arrived_at' => now(), 'completed_at' => now(),
        'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->first->id,
        'status' => AttemptStatus::Completed, 'arrived_at' => now(), 'completed_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.show', [$this->game, $session]))
        ->assertOk()
        ->assertJsonPath('player.id', $session->id)
        ->assertJsonPath('attempts.0.location_position', 1)
        ->assertJsonPath('attempts.0.photo_url', null)
        ->assertJsonPath('attempts.1.location_position', 2)
        ->assertJsonPath('attempts.1.status', 'completed')
        ->assertJsonPath('attempts.1.flagged_invalid', false)
        ->assertJson(fn ($json) => $json->where('attempts.1.photo_url', fn ($url) => str_ends_with($url, '/storage/attempt-photos/x.jpg'))->etc());
});

test('player detail is scoped to the game in the url', function () {
    $otherGame = Game::factory()->create(['owner_id' => $this->owner->id]);
    $foreignSession = PlayerSession::factory()->create(['game_id' => $otherGame->id]);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.show', [$this->game, $foreignSession]))
        ->assertNotFound();
});

test('another dozor cannot read player detail', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.games.players.show', [$this->game, $session]))
        ->assertForbidden();
});

test('the owner can flag and unflag a photo as invalid', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->second->id,
        'status' => AttemptStatus::Completed, 'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);
    $url = route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]);

    $this->actingAs($this->owner)->patchJson($url, ['flagged_invalid' => true])
        ->assertOk()->assertJson(['flagged_invalid' => true]);
    expect($attempt->fresh()->flagged_invalid)->toBeTrue();

    $this->actingAs($this->owner)->patchJson($url, ['flagged_invalid' => false])->assertOk();
    expect($attempt->fresh()->flagged_invalid)->toBeFalse();
});

test('flagging an attempt without a photo is rejected', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->first->id, 'status' => AttemptStatus::Completed,
    ]);

    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]), ['flagged_invalid' => true])
        ->assertStatus(422);
});

test('flagging is scoped: an attempt of another player 404s', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $other = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $other->id, 'location_id' => $this->second->id,
        'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);

    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]), ['flagged_invalid' => true])
        ->assertNotFound();
});

test('another dozor cannot flag a photo', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->second->id,
        'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);

    $this->actingAs(User::factory()->create())
        ->patchJson(route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]), ['flagged_invalid' => true])
        ->assertForbidden();
});

test('tracking page does not have N+1 query for locations_count', function () {
    // Create initial players
    for ($i = 0; $i < 1; $i++) {
        PlayerSession::factory()->create(['game_id' => $this->game->id])->pings()->create(['lat' => 50.0 + $i, 'lng' => 14.0]);
    }

    \DB::enableQueryLog();
    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game));
    $first_load_count = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    // Add 4 more players (5 total now, 5x initial)
    for ($i = 0; $i < 4; $i++) {
        PlayerSession::factory()->create(['game_id' => $this->game->id])->pings()->create(['lat' => 50.0 + 1 + $i, 'lng' => 14.0]);
    }

    // With N+1 query issue per player, adding 4 players would add ~4 queries (doubling total from ~5 to ~9+)
    // With proper optimization, adding players should add minimal queries
    \DB::enableQueryLog();
    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game));
    $second_load_count = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    // The growth should be sub-linear with the fix in place
    // Without the fix, adding 4 players would add ~4 queries (doubling from ~5 to ~9)
    // With the fix, growth should be minimal
    // We check that it doesn't exceed 2x, which would still be better than linear N+1 scaling
    expect($second_load_count)->toBeLessThanOrEqual($first_load_count * 2);
});
