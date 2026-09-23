<?php

use App\Models\Game;
use App\Models\User;

beforeEach(function () {
    // phpunit.xml uses the null broadcaster, which authorizes nothing.
    // Switch to a real Pusher-protocol broadcaster with dummy credentials:
    // channel auth only signs locally, no Reverb server is contacted.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    // Broadcast::channel() registers on the driver that was default at boot
    // (the null one), so re-register the channels on the reverb driver.
    require base_path('routes/channels.php');
});

function authorizeTrackingChannel($test, Game $game)
{
    return $test->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-games.{$game->id}.tracking",
    ]);
}

test('the game owner can subscribe to the tracking channel', function () {
    $owner = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    authorizeTrackingChannel($this->actingAs($owner), $game)
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test('another dozor cannot subscribe to the tracking channel', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    authorizeTrackingChannel($this->actingAs($stranger), $game)->assertForbidden();
});

test('a guest cannot subscribe to the tracking channel', function () {
    $game = Game::factory()->create();

    authorizeTrackingChannel($this, $game)->assertForbidden();
});
