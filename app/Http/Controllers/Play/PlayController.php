<?php

namespace App\Http\Controllers\Play;

use App\Events\PlayerProgressed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Play\JoinGameRequest;
use App\Models\Game;
use App\Models\PlayerSession;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PlayController extends Controller
{
    use ResolvesPlayerSession;

    public function show(Game $game)
    {
        $playerSession = $this->findActivePlayerSession($game);

        if (! $playerSession) {
            return Inertia::render('Play/Join', [
                'game' => ['name' => $game->name, 'slug' => $game->slug],
            ]);
        }

        return Inertia::render('Play/Show', [
            'game' => ['name' => $game->name, 'slug' => $game->slug],
            'locations' => $game->locations()->get()->map(fn ($location) => [
                'id' => $location->id,
                'order' => $location->order,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'arrival_radius_m' => $location->arrival_radius_m,
                'task_type' => $location->task_type->value,
            ])->values(),
            'player' => [
                'current_location_index' => $playerSession->current_location_index,
                'finished_at' => $playerSession->finished_at,
            ],
        ]);
    }

    public function join(JoinGameRequest $request, Game $game)
    {
        $playerSession = PlayerSession::create([
            'game_id' => $game->id,
            'nickname' => $request->validated('nickname'),
            'current_location_index' => 0,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        // A Reverb outage must never fail a player request (Global
        // Constraints, Plan C): report the broadcast failure but let the
        // join succeed regardless.
        rescue(fn () => PlayerProgressed::dispatch($playerSession, 'joined'));

        session(['player_session_id' => $playerSession->id]);

        return redirect()->route('play.show', $game->slug);
    }
}
