<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\PlayerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
    $this->player = PlayerSession::factory()->create(['game_id' => $this->game->id]);
});

test('one browser cannot flood the position pings', function () {
    $session = ['player_session_id' => $this->player->id];

    for ($i = 0; $i < 30; $i++) {
        $this->withSession($session)->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50, 'lng' => 14])->assertNoContent();
    }

    $this->withSession($session)->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50, 'lng' => 14])
        ->assertTooManyRequests();

    expect($this->player->pings()->count())->toBe(30);
});

test('before joining, a browser is limited by its session and its network', function () {
    $session = app('session')->driver();
    $session->setId(str_repeat('a', 40));
    $request = Request::create('/hra/x/join', 'POST', server: ['REMOTE_ADDR' => '10.0.0.7']);
    $request->setLaravelSession($session);

    $limits = RateLimiter::limiter('play-join')($request);

    expect(collect($limits)->map(fn ($limit) => [$limit->key, $limit->maxAttempts])->all())->toBe([
        ['play-join|s:'.str_repeat('a', 40), 10],
        ['play-join|ip:10.0.0.7', 120],
    ]);
});

test('players on one shared network are not limited by each other', function () {
    foreach (PlayerSession::factory()->count(5)->create(['game_id' => $this->game->id]) as $player) {
        for ($i = 0; $i < 10; $i++) {
            $this->withSession(['player_session_id' => $player->id])
                ->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50, 'lng' => 14])
                ->assertNoContent();
        }
    }
});
