<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\PlayerSession;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->owner->id, 'status' => GameStatus::Published]);
    $this->player = PlayerSession::factory()->create(['game_id' => $this->game->id]);
});

function pingAt(PlayerSession $player, float $lat, float $lng)
{
    return $player->pings()->create(['lat' => $lat, 'lng' => $lng]);
}

test('the dozor gets a player\'s walked path in order', function () {
    pingAt($this->player, 50.1, 14.1);
    pingAt($this->player, 50.2, 14.2);
    $last = pingAt($this->player, 50.3, 14.3);
    pingAt(PlayerSession::factory()->create(['game_id' => $this->game->id]), 49.0, 13.0);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.path', [$this->game, $this->player]))
        ->assertOk()
        ->assertExactJson([
            'points' => [[50.1, 14.1], [50.2, 14.2], [50.3, 14.3]],
            'last_id' => $last->id,
        ]);
});

test('only pings after the given id are returned', function () {
    $first = pingAt($this->player, 50.1, 14.1);
    $second = pingAt($this->player, 50.2, 14.2);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.path', [$this->game, $this->player, 'after' => $first->id]))
        ->assertExactJson(['points' => [[50.2, 14.2]], 'last_id' => $second->id]);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.path', [$this->game, $this->player, 'after' => $second->id]))
        ->assertExactJson(['points' => [], 'last_id' => $second->id]);
});

test('another dozor cannot read the path', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.games.players.path', [$this->game, $this->player]))
        ->assertForbidden();
});

test('a player of another game is not found', function () {
    $other = PlayerSession::factory()->create();

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.path', [$this->game, $other]))
        ->assertNotFound();
});
