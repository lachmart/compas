<?php

use App\Enums\GameStatus;
use App\Events\PlayerPositionUpdated;
use App\Events\PlayerProgressed;
use App\Models\Game;
use App\Models\PlayerSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Broadcasting a ShouldBroadcast event queues a real Illuminate\Broadcasting\
// BroadcastEvent job that serializes our event (and its Eloquent model via
// SerializesModels). `sync` never round-trips through serialize/unserialize,
// so this needs a real queue driver to reproduce: if the player is deleted
// (with their pings/attempts cascading) before the job runs, unserializing
// throws ModelNotFoundException — deleteWhenMissingModels must make the job
// vanish quietly instead of failing forever.
beforeEach(function () {
    config()->set(['queue.default' => 'database']);
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
});

test('a queued player.progressed broadcast for a deleted player is dropped, not failed', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    event(new PlayerProgressed($session, 'joined'));
    expect(DB::table('jobs')->count())->toBe(1);

    $session->delete();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a queued player.position broadcast for a deleted player is dropped, not failed', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $ping = $session->pings()->create(['lat' => 50.08, 'lng' => 14.43]);

    event(new PlayerPositionUpdated($ping->fresh()));
    expect(DB::table('jobs')->count())->toBe(1);

    $session->delete();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});
