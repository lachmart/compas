<?php

namespace App\Http\Controllers\Play;

use App\Enums\AttemptStatus;
use App\Enums\Avatar;
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
                'taken_avatars' => $game->takenAvatars(),
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
                // Only whether a text board exists; its content comes from
                // journal() once the station is done.
                'has_info' => $location->hasInfo(),
            ])->values(),
            'player' => [
                'current_location_index' => $playerSession->current_location_index,
                'finished_at' => $playerSession->finished_at,
                'avatar' => $playerSession->avatar?->value,
            ],
        ]);
    }

    /**
     * The text boards of the stations this player has completed, to read
     * again. Boards of stations ahead are never sent (they may hold the clue
     * to a password).
     */
    public function journal(Game $game)
    {
        $playerSession = $this->resolvePlayerSession($game);

        $completed = $playerSession->attempts()
            ->where('status', AttemptStatus::Completed)
            ->pluck('location_id')
            ->flip();

        $entries = [];

        foreach ($game->locations()->get()->values() as $index => $location) {
            if (! $completed->has($location->id) || ! $location->hasInfo()) {
                continue;
            }

            $entries[] = [
                'id' => $location->id,
                'position' => $index + 1,
                'title' => $location->info_title,
                'text' => $location->info_text,
                'images' => collect($location->infoImages())->map(fn ($path) => Storage::disk('public')->url($path))->values()->all(),
            ];
        }

        return response()->json(['entries' => $entries]);
    }

    public function join(JoinGameRequest $request, Game $game)
    {
        $avatar = Avatar::from($request->validated('avatar'));
        $nickname = trim((string) $request->validated('nickname'));

        $playerSession = PlayerSession::create([
            'game_id' => $game->id,
            'nickname' => $nickname !== '' ? $nickname : $avatar->label(),
            'avatar' => $avatar,
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
