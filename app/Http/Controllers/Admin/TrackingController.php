<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Support\TrackedPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                    ? Storage::disk('public')->url($attempt->submitted_photo_path)
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

    public function flag(Request $request, Game $game, PlayerSession $playerSession, LocationAttempt $attempt)
    {
        $this->authorize('update', $game);

        abort_unless($attempt->submitted_photo_path, 422);

        $validated = $request->validate(['flagged_invalid' => ['required', 'boolean']]);

        $attempt->update(['flagged_invalid' => $validated['flagged_invalid']]);

        return response()->json(['flagged_invalid' => $attempt->flagged_invalid]);
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
