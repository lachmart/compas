<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->owner->id, 'status' => GameStatus::Published]);
    $this->location = Location::factory()->create(['game_id' => $this->game->id, 'order' => 1]);
    $this->player = PlayerSession::factory()->create(['game_id' => $this->game->id]);
});

test('the owner can hide and unhide a player', function () {
    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.hide', [$this->game, $this->player]))
        ->assertOk()->assertJsonPath('player.hidden', true);
    expect($this->player->fresh()->hidden_at)->not->toBeNull();

    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.unhide', [$this->game, $this->player]))
        ->assertOk()->assertJsonPath('player.hidden', false);
    expect($this->player->fresh()->hidden_at)->toBeNull();
});

test('hidden players are still listed for tracking, flagged as hidden', function () {
    $this->player->update(['hidden_at' => now()]);

    $this->actingAs($this->owner)->getJson(route('admin.games.tracking.players', $this->game))
        ->assertJsonPath('players.0.hidden', true);
});

test('deleting a player removes their attempts, pings and photos', function () {
    Storage::disk('public')->put('attempt-photos/p.jpg', 'x');
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $this->player->id, 'location_id' => $this->location->id,
        'submitted_photo_path' => 'attempt-photos/p.jpg',
    ]);
    $this->player->pings()->create(['lat' => 50, 'lng' => 14]);

    $this->actingAs($this->owner)
        ->deleteJson(route('admin.games.players.destroy', [$this->game, $this->player]))
        ->assertNoContent();

    expect(PlayerSession::find($this->player->id))->toBeNull()
        ->and(LocationAttempt::find($attempt->id))->toBeNull();
    Storage::disk('public')->assertMissing('attempt-photos/p.jpg');
});

test('bulk actions hide, unhide and delete only players of this game', function () {
    $second = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $foreign = PlayerSession::factory()->create();

    $this->actingAs($this->owner)
        ->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'hide', 'ids' => [$this->player->id, $second->id]])
        ->assertOk()->assertJson(['affected' => 2]);
    expect($second->fresh()->hidden_at)->not->toBeNull();

    $this->actingAs($this->owner)
        ->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'delete', 'ids' => [$this->player->id, $foreign->id]])
        ->assertJsonValidationErrors('ids.1');
    expect(PlayerSession::find($foreign->id))->not->toBeNull();

    $this->actingAs($this->owner)
        ->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'delete', 'ids' => [$this->player->id, $second->id]])
        ->assertOk()->assertJson(['affected' => 2]);
    expect(PlayerSession::where('game_id', $this->game->id)->count())->toBe(0);
});

test('another dozor cannot hide, delete or bulk-manage players', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->patchJson(route('admin.games.players.hide', [$this->game, $this->player]))->assertForbidden();
    $this->actingAs($stranger)->deleteJson(route('admin.games.players.destroy', [$this->game, $this->player]))->assertForbidden();
    $this->actingAs($stranger)->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'delete', 'ids' => [$this->player->id]])->assertForbidden();
    expect(PlayerSession::find($this->player->id))->not->toBeNull();
});

test('a player of another game cannot be targeted through this game url', function () {
    $foreign = PlayerSession::factory()->create();

    $this->actingAs($this->owner)
        ->deleteJson(route('admin.games.players.destroy', [$this->game, $foreign]))
        ->assertNotFound();
});

test('a hidden player reappears when they complete a location', function () {
    $this->player->update(['hidden_at' => now()]);

    $this->withSession(['player_session_id' => $this->player->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $this->location->id]))
        ->assertOk();

    expect($this->player->fresh()->hidden_at)->toBeNull();
});

test('a hidden player stays hidden while only sending position pings', function () {
    $this->player->update(['hidden_at' => now()]);

    $this->withSession(['player_session_id' => $this->player->id])
        ->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50.08, 'lng' => 14.43])
        ->assertNoContent();

    expect($this->player->fresh()->hidden_at)->not->toBeNull();
});
