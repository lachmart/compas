<?php

namespace App\Http\Controllers\Play;

use App\Enums\AttemptStatus;
use App\Enums\Avatar;
use App\Events\PlayerProgressed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Play\JoinGameRequest;
use App\Models\Game;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Support\AttemptPhotos;
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
     * The stations this player has completed that have something to look at
     * again: the text board and/or the player's own photo. Boards of
     * stations ahead are never sent (they may hold the clue to a password).
     */
    public function journal(Game $game)
    {
        $playerSession = $this->resolvePlayerSession($game);

        $completed = $playerSession->attempts()
            ->where('status', AttemptStatus::Completed)
            ->get()
            ->keyBy('location_id');

        $entries = [];

        foreach ($game->locations()->get()->values() as $index => $location) {
            $attempt = $completed->get($location->id);
            $hasPhoto = $attempt?->submitted_photo_path !== null;

            if (! $attempt || (! $location->hasInfo() && ! $hasPhoto)) {
                continue;
            }

            $entries[] = [
                'id' => $location->id,
                'position' => $index + 1,
                ...($location->infoBoard() ?? ['title' => null, 'text' => null, 'images' => []]),
                'photo' => $hasPhoto ? route('play.photo', [$game->slug, $attempt]) : null,
            ];
        }

        return response()->json(['entries' => $entries]);
    }

    /** One of the player's own task photos — never anyone else's. */
    public function photo(Game $game, LocationAttempt $attempt)
    {
        $playerSession = $this->resolvePlayerSession($game);

        abort_unless($attempt->player_session_id === $playerSession->id && $attempt->submitted_photo_path, 404);

        return AttemptPhotos::response($attempt->submitted_photo_path);
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
