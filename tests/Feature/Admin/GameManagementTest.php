<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot access the games dashboard', function () {
    $this->get(route('admin.games.index'))->assertRedirect(route('login'));
});

test('a dozor can create a game and gets a unique slug', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.games.store'), [
        'name' => 'Lesní dobrodružství',
    ]);

    $game = Game::first();

    $response->assertRedirect(route('admin.games.edit', $game));
    expect($game->owner_id)->toBe($user->id)
        ->and($game->slug)->toBe('lesni-dobrodruzstvi')
        ->and($game->status)->toBe(GameStatus::Draft);
});

test('a dozor only sees their own games', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Game::factory()->create(['owner_id' => $owner->id]);
    Game::factory()->create(['owner_id' => $stranger->id]);

    $this->actingAs($owner)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Games/Index')->has('games', 1));
});

test("a dozor cannot edit another dozor's game", function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($stranger)->get(route('admin.games.edit', $game))->assertForbidden();
    $this->actingAs($stranger)->put(route('admin.games.update', $game), [
        'name' => 'x', 'slug' => 'x',
    ])->assertForbidden();
});

test('a dozor can update the game name and slug', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->put(route('admin.games.update', $game), [
        'name' => 'Nový název',
        'slug' => 'novy-nazev',
    ])->assertRedirect();

    expect($game->fresh()->name)->toBe('Nový název')
        ->and($game->fresh()->slug)->toBe('novy-nazev');
});

test('updating a game rejects a slug with an invalid format', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->put(route('admin.games.update', $game), [
        'name' => 'Nový název',
        'slug' => 'Invalid Slug/here',
    ])->assertSessionHasErrors('slug');
});

test('updating a game accepts a valid lowercase-hyphenated slug', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->put(route('admin.games.update', $game), [
        'name' => 'Nový název',
        'slug' => 'novy-nazev-2',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($game->fresh()->slug)->toBe('novy-nazev-2');
});

test('a dozor can publish and unpublish a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id, 'status' => GameStatus::Draft]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);

    $this->actingAs($user)->patch(route('admin.games.publish', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Published);

    $this->actingAs($user)->patch(route('admin.games.unpublish', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Draft);
});

test('a game with no locations cannot be published', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id, 'status' => GameStatus::Draft]);

    $this->actingAs($user)->patch(route('admin.games.publish', $game))
        ->assertStatus(422);
    expect($game->fresh()->status)->toBe(GameStatus::Draft);
});

test('a dozor can delete their own game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->delete(route('admin.games.destroy', $game))
        ->assertRedirect(route('admin.games.index'));

    expect(Game::find($game->id))->toBeNull();
});

test('deleting a game also deletes task images and player attempt photos', function () {
    Storage::fake('public');
    Storage::disk('public')->put('task-images/task.jpg', 'x');
    Storage::disk('public')->put('attempt-photos/attempt.jpg', 'x');

    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create([
        'game_id' => $game->id,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['task-images/task.jpg'], 'correct_index' => 0],
    ]);
    $player = PlayerSession::factory()->create(['game_id' => $game->id]);
    LocationAttempt::factory()->create([
        'player_session_id' => $player->id,
        'location_id' => $location->id,
        'submitted_photo_path' => 'attempt-photos/attempt.jpg',
    ]);

    $this->actingAs($user)->delete(route('admin.games.destroy', $game))
        ->assertRedirect(route('admin.games.index'));

    Storage::disk('public')->assertMissing('task-images/task.jpg');
    Storage::disk('public')->assertMissing('attempt-photos/attempt.jpg');
});

test('the edit page lists locations with image urls and the correct index', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create([
        'game_id' => $game->id, 'order' => 1, 'name' => 'Studánka',
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['task-images/a.jpg', 'task-images/b.jpg', 'task-images/c.jpg'], 'correct_index' => 2],
    ]);

    $this->actingAs($user)->get(route('admin.games.edit', $game))
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Games/Edit')
            ->has('locations', 1)
            ->where('locations.0.name', 'Studánka')
            ->where('locations.0.correct_index', 2)
            ->where('locations.0.images.0.path', 'task-images/a.jpg')
            ->where('locations.0.images.0.url', fn ($url) => str_ends_with($url, '/storage/task-images/a.jpg'))
            ->where('locations.0.reference_image', null));
});

test('the games list includes each game\'s location count', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->count(2)->create(['game_id' => $game->id]);

    $this->actingAs($user)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page->where('games.0.locations_count', 2));
});

test('the games list includes player stats, last activity and the route', function () {
    $this->travelTo(now()->setTime(12, 0));
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'lat' => 50.2, 'lng' => 14.2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'lat' => 50.1, 'lng' => 14.1]);
    PlayerSession::factory()->create(['game_id' => $game->id, 'last_seen_at' => now()->subSeconds(30)]);
    PlayerSession::factory()->create(['game_id' => $game->id, 'last_seen_at' => now()->subHour()]);
    PlayerSession::factory()->create(['game_id' => $game->id, 'last_seen_at' => now()->subMinutes(10), 'finished_at' => now()->subMinutes(10)]);

    $this->actingAs($user)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page
            ->where('games.0.locations_count', 2)
            ->where('games.0.players_count', 3)
            ->where('games.0.active_players_count', 1)
            ->where('games.0.finished_players_count', 1)
            ->where('games.0.last_activity_at', fn ($iso) => str_starts_with($iso, now()->subSeconds(30)->format('Y-m-d\TH:i:s')))
            ->where('games.0.route', [[50.1, 14.1], [50.2, 14.2]]));
});

test('a game nobody played has no last activity and an empty route', function () {
    $user = User::factory()->create();
    Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page
            ->where('games.0.players_count', 0)
            ->where('games.0.last_activity_at', null)
            ->where('games.0.route', []));
});

test('the games list query count does not grow with the number of games', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $make = function () use ($user) {
        $game = Game::factory()->create(['owner_id' => $user->id]);
        Location::factory()->count(2)->create(['game_id' => $game->id]);
        PlayerSession::factory()->create(['game_id' => $game->id]);
    };
    $make();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->get(route('admin.games.index'))->assertOk();
    $one = count(DB::getQueryLog());

    $make();
    $make();
    $make();
    DB::flushQueryLog();
    $this->get(route('admin.games.index'))->assertOk();
    $four = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($four)->toBe($one);
});

test('the edit page provides the play url and player count', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id, 'slug' => 'lesni-hra']);
    PlayerSession::factory()->count(2)->create(['game_id' => $game->id]);

    $this->actingAs($user)->get(route('admin.games.edit', $game))
        ->assertInertia(fn ($page) => $page
            ->where('play_url', route('play.show', 'lesni-hra'))
            ->where('players_count', 2));
});
