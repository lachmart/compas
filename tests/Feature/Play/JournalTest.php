<?php

use App\Enums\AttemptStatus;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;

beforeEach(function () {
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
    $this->first = Location::factory()->create(['game_id' => $this->game->id, 'order' => 1, 'info_title' => 'Rybník', 'info_text' => 'Žije tu žába.', 'info_images' => ['task-images/r.jpg']]);
    $this->plain = Location::factory()->create(['game_id' => $this->game->id, 'order' => 2]);
    $this->ahead = Location::factory()->create(['game_id' => $this->game->id, 'order' => 3, 'info_text' => 'Heslo je sova.']);
    $this->player = PlayerSession::factory()->create(['game_id' => $this->game->id, 'current_location_index' => 2]);

    foreach ([$this->first, $this->plain] as $done) {
        LocationAttempt::factory()->create(['player_session_id' => $this->player->id, 'location_id' => $done->id, 'status' => AttemptStatus::Completed]);
    }
    LocationAttempt::factory()->create(['player_session_id' => $this->player->id, 'location_id' => $this->ahead->id, 'status' => AttemptStatus::Arrived]);
});

test('the journal lists the text boards of completed stations only', function () {
    $response = $this->withSession(['player_session_id' => $this->player->id])
        ->getJson(route('play.journal', $this->game->slug))
        ->assertOk()
        ->assertJsonCount(1, 'entries')
        ->assertJsonPath('entries.0.position', 1)
        ->assertJsonPath('entries.0.title', 'Rybník')
        ->assertJsonPath('entries.0.text', 'Žije tu žába.');

    expect($response->json('entries.0.images.0'))->toEndWith('/storage/task-images/r.jpg')
        ->and($response->getContent())->not->toContain('sova');
});

test('the journal needs the player\'s own session', function () {
    $this->getJson(route('play.journal', $this->game->slug))->assertForbidden();

    $other = PlayerSession::factory()->create();
    $this->withSession(['player_session_id' => $other->id])
        ->getJson(route('play.journal', $this->game->slug))
        ->assertForbidden();
});

test('the game screen only says which stations have a text board', function () {
    $this->withoutVite();

    $this->withSession(['player_session_id' => $this->player->id])
        ->get(route('play.show', $this->game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Show')
            ->where('locations.0.has_info', true)
            ->where('locations.1.has_info', false)
            ->where('locations.2.has_info', true)
            ->missing('locations.2.info_text'));
});
