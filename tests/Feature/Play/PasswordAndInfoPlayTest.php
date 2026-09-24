<?php

use App\Enums\AttemptStatus;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;

beforeEach(function () {
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
    $this->location = Location::factory()->create([
        'game_id' => $this->game->id,
        'order' => 1,
        'task_type' => 'password',
        'task_payload' => ['answers' => ['Žába']],
    ]);
    Location::factory()->create(['game_id' => $this->game->id, 'order' => 2]);
    $this->player = PlayerSession::factory()->create(['game_id' => $this->game->id, 'current_location_index' => 0]);
    $this->asPlayer = fn () => $this->withSession(['player_session_id' => $this->player->id]);
});

test('arriving shows the password task but never the answer', function () {
    $response = ($this->asPlayer)()->postJson(route('play.attempts.arrive', [$this->game->slug, $this->location->id]));

    $response->assertOk()
        ->assertJson(['attempt_status' => 'arrived', 'task_type' => 'password', 'info' => null]);
    expect($response->getContent())->not->toContain('aba');
});

test('a wrong password keeps the player at the station', function () {
    ($this->asPlayer)()->postJson(route('play.attempts.arrive', [$this->game->slug, $this->location->id]));

    ($this->asPlayer)()->postJson(route('play.attempts.password', [$this->game->slug, $this->location->id]), ['answer' => 'rosnicka'])
        ->assertOk()
        ->assertJson(['correct' => false]);

    expect($this->player->fresh()->current_location_index)->toBe(0);
});

test('the right password, however typed, lets the player on', function () {
    ($this->asPlayer)()->postJson(route('play.attempts.arrive', [$this->game->slug, $this->location->id]));

    ($this->asPlayer)()->postJson(route('play.attempts.password', [$this->game->slug, $this->location->id]), ['answer' => '  ZABA '])
        ->assertOk()
        ->assertJson(['correct' => true, 'player' => ['current_location_index' => 1]]);

    expect(LocationAttempt::first()->status)->toBe(AttemptStatus::Completed);
});

test('the password endpoint only works on password stations', function () {
    $this->location->update(['task_type' => 'arrival_only', 'task_payload' => null]);
    LocationAttempt::factory()->create(['player_session_id' => $this->player->id, 'location_id' => $this->location->id]);

    ($this->asPlayer)()->postJson(route('play.attempts.password', [$this->game->slug, $this->location->id]), ['answer' => 'zaba'])
        ->assertStatus(422);
});

test('arriving at a station with a text board returns it', function () {
    $this->location->update([
        'info_title' => 'Rybník',
        'info_text' => "Žije tu žába.\n\nA taky kachny.",
        'info_images' => ['task-images/zaba.jpg'],
    ]);

    ($this->asPlayer)()->postJson(route('play.attempts.arrive', [$this->game->slug, $this->location->id]))
        ->assertOk()
        ->assertJsonPath('info.title', 'Rybník')
        ->assertJsonPath('info.text', "Žije tu žába.\n\nA taky kachny.")
        ->assertJson(fn ($json) => $json->where('info.images.0', fn ($url) => str_ends_with($url, '/storage/task-images/zaba.jpg'))->etc());
});
