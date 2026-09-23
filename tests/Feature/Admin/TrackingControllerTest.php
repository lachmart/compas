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

    // Measure query count for first request (1 player)
    \DB::flushQueryLog();
    \DB::enableQueryLog();
    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game));
    $first_load_count = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    // Add 4 more players (5 total now, 5x initial)
    for ($i = 0; $i < 4; $i++) {
        PlayerSession::factory()->create(['game_id' => $this->game->id])->pings()->create(['lat' => 50.0 + 1 + $i, 'lng' => 14.0]);
    }

    // Measure query count for second request (5 players)
    // With N+1 issue, each additional player adds a COUNT query, so 5 players would cost more than 1 player
    // With the fix, query cost should be identical since locations_count is pre-loaded and shared
    \DB::flushQueryLog();
    \DB::enableQueryLog();
    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game));
    $second_load_count = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    // The query counts should be equal: both requests should cost the same number of queries
    // This proves locations_count doesn't add per-player COUNT queries
    expect($second_load_count)->toBe($first_load_count);
});

test('the owner can poll the current player list as json', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id, 'nickname' => 'Anna', 'current_location_index' => 1]);
    $session->pings()->create(['lat' => 50.08, 'lng' => 14.43]);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.tracking.players', $this->game))
        ->assertOk()
        ->assertJsonCount(1, 'players')
        ->assertJsonPath('players.0.id', $session->id)
        ->assertJsonPath('players.0.nickname', 'Anna')
        ->assertJsonPath('players.0.current_location_index', 1)
        ->assertJsonPath('players.0.locations_count', 2)
        ->assertJsonPath('players.0.position.lat', 50.08);
});

test('another dozor cannot poll the player list', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.games.tracking.players', $this->game))
        ->assertForbidden();
});

test('polling the player list does not grow queries per player', function () {
    PlayerSession::factory()->create(['game_id' => $this->game->id])->pings()->create(['lat' => 50, 'lng' => 14]);
    $url = route('admin.games.tracking.players', $this->game);
    $this->actingAs($this->owner);

    \DB::enableQueryLog();
    \DB::flushQueryLog();
    $this->getJson($url)->assertOk();
    $onePlayer = count(\DB::getQueryLog());

    PlayerSession::factory()->count(4)->create(['game_id' => $this->game->id])
        ->each(fn ($s) => $s->pings()->create(['lat' => 50, 'lng' => 14]));
    \DB::flushQueryLog();
    $this->getJson($url)->assertOk();
    $fivePlayers = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    expect($fivePlayers)->toBe($onePlayer);
});

test('tracking exposes location names', function () {
    $this->first->update(['name' => 'Studánka']);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->first->id,
        'status' => AttemptStatus::Completed,
    ]);

    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game))
        ->assertInertia(fn ($page) => $page
            ->where('locations.0.name', 'Studánka')
            ->where('locations.1.name', null));

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.show', [$this->game, $session]))
        ->assertJsonPath('attempts.0.location_name', 'Studánka');
});
