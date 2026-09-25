<?php

use App\Enums\Avatar;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\PlayerSession;

beforeEach(function () {
    $this->withoutVite();
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
});

function playerWith(Game $game, Avatar $avatar, array $attributes = []): PlayerSession
{
    return PlayerSession::factory()->create([
        'game_id' => $game->id,
        'avatar' => $avatar,
        'last_seen_at' => now(),
        ...$attributes,
    ]);
}

test('the join page lists animals held by active players of this game', function () {
    playerWith($this->game, Avatar::Fox);
    playerWith($this->game, Avatar::Owl, ['finished_at' => now()]);
    playerWith($this->game, Avatar::Bear, ['hidden_at' => now()]);
    playerWith($this->game, Avatar::Frog, ['last_seen_at' => now()->subMinutes(31)]);
    playerWith(Game::factory()->create(['status' => GameStatus::Published]), Avatar::Deer);

    $this->get(route('play.show', $this->game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Join')
            ->where('taken_avatars', ['fox']));
});

test('a player cannot join with an animal another active player has', function () {
    playerWith($this->game, Avatar::Fox);

    $this->post(route('play.join', $this->game->slug), ['avatar' => 'fox'])
        ->assertSessionHasErrors('avatar');

    expect(PlayerSession::count())->toBe(1);
});

test('an animal of a finished, hidden or long-silent player is free again', function () {
    playerWith($this->game, Avatar::Owl, ['finished_at' => now()]);
    playerWith($this->game, Avatar::Bear, ['hidden_at' => now()]);
    playerWith($this->game, Avatar::Frog, ['last_seen_at' => now()->subMinutes(31)]);

    foreach (['owl', 'bear', 'frog'] as $avatar) {
        $this->flushSession();
        $this->post(route('play.join', $this->game->slug), ['avatar' => $avatar])
            ->assertSessionHasNoErrors();
    }

    expect(PlayerSession::count())->toBe(6);
});

test('when every animal is taken, any animal can be picked again', function () {
    foreach (Avatar::cases() as $avatar) {
        playerWith($this->game, $avatar);
    }

    $this->get(route('play.show', $this->game->slug))
        ->assertInertia(fn ($page) => $page->where('taken_avatars', []));

    $this->post(route('play.join', $this->game->slug), ['avatar' => 'fox'])
        ->assertSessionHasNoErrors();
});
