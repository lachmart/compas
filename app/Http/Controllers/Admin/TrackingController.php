<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Support\AttemptPhotos;
use App\Support\TrackedPlayer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrackingController extends Controller
{
    public function show(Game $game)
    {
        $this->authorize('view', $game);

        return Inertia::render('Admin/Games/Tracking', [
            'game' => ['id' => $game->id, 'name' => $game->name, 'slug' => $game->slug, 'status' => $game->status->value],
            'play_url' => route('play.show', $game->slug),
            'locations' => $game->locations()->get()->values()->map(fn ($location, $index) => [
                'id' => $location->id,
                'position' => $index + 1,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'task_type' => $location->task_type->value,
                'name' => $location->name,
            ]),
            'players' => $this->trackedPlayers($game),
        ]);
    }

    public function players(Game $game)
    {
        $this->authorize('view', $game);

        return response()->json(['players' => $this->trackedPlayers($game)]);
    }

    /**
     * The player's walked path as [lat, lng] pairs in ping order. `after`
     * (a ping id, the previous response's last_id) returns only newer
     * points, so the open detail can keep the line growing cheaply.
     */
    public function path(Request $request, Game $game, PlayerSession $playerSession)
    {
        $this->authorize('view', $game);

        $after = max(0, (int) $request->query('after', 0));

        $pings = $playerSession->pings()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get(['id', 'lat', 'lng']);

        return response()->json([
            'points' => $pings->map(fn ($ping) => [(float) $ping->lat, (float) $ping->lng])->values(),
            'last_id' => $pings->last()?->id ?? $after,
        ]);
    }

    public function player(Game $game, PlayerSession $playerSession)
    {
        $this->authorize('view', $game);

        $positions = $game->locations()->pluck('id')->flip();

        $attempts = $playerSession->attempts()->with('location')->get()
            ->map(fn (LocationAttempt $attempt) => [
                'id' => $attempt->id,
                'location_position' => $positions[$attempt->location_id] + 1,
                'task_type' => $attempt->location->task_type->value,
                'status' => $attempt->status->value,
                'arrived_at' => $attempt->arrived_at?->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
                'photo_url' => $attempt->submitted_photo_path
                    ? route('admin.games.players.attempts.photo', [$game, $playerSession, $attempt])
                    : null,
                'flagged_invalid' => $attempt->flagged_invalid,
                'location_name' => $attempt->location->name,
            ])
            ->sortBy('location_position')
            ->values();

        return response()->json([
            'player' => TrackedPlayer::make($playerSession),
            'attempts' => $attempts,
        ]);
    }

    /** A player's task photo — only ever served to the game's dozor. */
    public function photo(Game $game, PlayerSession $playerSession, LocationAttempt $attempt)
    {
        $this->authorize('view', $game);

        abort_unless($attempt->submitted_photo_path, 404);

        return AttemptPhotos::response($attempt->submitted_photo_path);
    }

    public function flag(Request $request, Game $game, PlayerSession $playerSession, LocationAttempt $attempt)
    {
        $this->authorize('update', $game);

        abort_unless($attempt->submitted_photo_path, 422);

        $validated = $request->validate(['flagged_invalid' => ['required', 'boolean']]);

        $attempt->update(['flagged_invalid' => $validated['flagged_invalid']]);

        return response()->json(['flagged_invalid' => $attempt->flagged_invalid]);
    }

    public function hide(Game $game, PlayerSession $playerSession)
    {
        $this->authorize('update', $game);

        $playerSession->forceFill(['hidden_at' => now()])->save();

        return response()->json(['player' => TrackedPlayer::make($playerSession->fresh())]);
    }

    public function unhide(Game $game, PlayerSession $playerSession)
    {
        $this->authorize('update', $game);

        $playerSession->forceFill(['hidden_at' => null])->save();

        return response()->json(['player' => TrackedPlayer::make($playerSession->fresh())]);
    }

    public function destroy(Game $game, PlayerSession $playerSession)
    {
        $this->authorize('update', $game);

        $photos = $playerSession->attempts()->whereNotNull('submitted_photo_path')->pluck('submitted_photo_path');

        $playerSession->delete();

        AttemptPhotos::delete($photos);

        return response()->noContent();
    }

    public function bulk(Request $request, Game $game)
    {
        $this->authorize('update', $game);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:hide,unhide,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', Rule::exists('player_sessions', 'id')->where('game_id', $game->id)],
        ]);

        $query = $game->playerSessions()->whereIn('id', $validated['ids']);

        $affected = match ($validated['action']) {
            'hide' => $query->update(['hidden_at' => now()]),
            'unhide' => $query->update(['hidden_at' => null]),
            'delete' => $this->bulkDelete($query),
        };

        return response()->json(['affected' => $affected]);
    }

    private function bulkDelete($query): int
    {
        $playerSessions = $query->get();

        $photos = LocationAttempt::whereIn('player_session_id', $playerSessions->pluck('id'))
            ->whereNotNull('submitted_photo_path')
            ->pluck('submitted_photo_path');

        $count = $playerSessions->count();

        PlayerSession::whereIn('id', $playerSessions->pluck('id'))->delete();

        AttemptPhotos::delete($photos);

        return $count;
    }

    private function trackedPlayers(Game $game)
    {
        // Load the count once and share this game instance across all sessions,
        // so TrackedPlayer::make() doesn't run a COUNT query per player.
        $game->loadCount('locations');

        return $game->playerSessions()
            ->with('latestPing')
            ->orderBy('started_at')
            ->get()
            ->map(function (PlayerSession $session) use ($game) {
                $session->setRelation('game', $game);

                return TrackedPlayer::make($session);
            });
    }
}
