<?php

use App\Enums\GameStatus;
use App\Events\PlayerPositionUpdated;
use App\Events\PlayerProgressed;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([PlayerPositionUpdated::class, PlayerProgressed::class]);
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
});

function addLocation(Game $game, int $order, string $type = 'arrival_only'): Location
{
    return Location::factory()->create([
        'game_id' => $game->id, 'order' => $order, 'task_type' => $type,
        'task_payload' => $type === 'pick_image'
            ? ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2]
            : [],
    ]);
}

test('a ping broadcasts the new position on the game tracking channel', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50.08, 'lng' => 14.43, 'heading' => 12]);

    Event::assertDispatched(PlayerPositionUpdated::class, function (PlayerPositionUpdated $event) use ($session) {
        $payload = $event->broadcastWith();

        return $event->broadcastOn()->name === 'private-games.'.$this->game->id.'.tracking'
            && $event->broadcastAs() === 'player.position'
            && $payload['player_session_id'] === $session->id
            && $payload['lat'] === 50.08
            && $payload['lng'] === 14.43
            && $payload['heading'] === 12.0;
    });
});

test('joining broadcasts a joined progress event', function () {
    addLocation($this->game, 1);

    $this->post(route('play.join', $this->game->slug), ['avatar' => 'fox', 'nickname' => 'Anna']);

    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'joined'
        && $e->broadcastWith()['player']['nickname'] === 'Anna'
        && $e->broadcastAs() === 'player.progressed');
});

test('completing a non-final location broadcasts completed_location', function () {
    $first = addLocation($this->game, 1);
    addLocation($this->game, 2);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $first->id]))->assertOk();

    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'completed_location'
        && $e->broadcastWith()['player']['current_location_index'] === 1);
});

test('completing the final location broadcasts finished', function () {
    $only = addLocation($this->game, 1);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $only->id]))->assertOk();

    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'finished'
        && $e->broadcastWith()['player']['finished_at'] !== null);
});

test('arriving at a task location without completing it broadcasts nothing', function () {
    $pick = addLocation($this->game, 1, 'pick_image');
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $pick->id]))->assertOk();

    Event::assertNotDispatched(PlayerProgressed::class);
});

test('a wrong pick_image answer broadcasts nothing, a correct one broadcasts progress', function () {
    $pick = addLocation($this->game, 1, 'pick_image');
    addLocation($this->game, 2);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $as = $this->withSession(['player_session_id' => $session->id]);
    $as->postJson(route('play.attempts.arrive', [$this->game->slug, $pick->id]));

    $as->postJson(route('play.attempts.answer', [$this->game->slug, $pick->id]), ['selected_index' => 0]);
    Event::assertNotDispatched(PlayerProgressed::class);

    $as->postJson(route('play.attempts.answer', [$this->game->slug, $pick->id]), ['selected_index' => 2]);
    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'completed_location');
});

test('broadcast payloads never leak the pick_image answer', function () {
    $pick = addLocation($this->game, 1, 'pick_image');
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $event = new PlayerProgressed($session, 'joined');

    expect(json_encode($event->broadcastWith()))->not->toContain('correct_index');
});
