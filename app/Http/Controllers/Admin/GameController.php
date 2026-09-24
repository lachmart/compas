<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameRequest;
use App\Http\Requests\Admin\UpdateGameRequest;
use App\Models\Game;
use App\Support\SlugGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $activeSince = now()->subSeconds(120);

        $games = $request->user()->games()
            ->withCount([
                'locations',
                'playerSessions as players_count',
                'playerSessions as active_players_count' => fn ($q) => $q
                    ->whereNull('finished_at')->where('last_seen_at', '>=', $activeSince),
                'playerSessions as finished_players_count' => fn ($q) => $q->whereNotNull('finished_at'),
            ])
            ->withMax('playerSessions as last_activity_at', 'last_seen_at')
            ->withCasts(['last_activity_at' => 'datetime'])
            ->with(['locations:id,game_id,lat,lng,order'])
            ->latest()
            ->get()
            ->map(fn (Game $game) => [
                ...$game->only(['id', 'name', 'slug', 'status', 'created_at', 'updated_at']),
                'locations_count' => $game->locations_count,
                'players_count' => $game->players_count,
                'active_players_count' => $game->active_players_count,
                'finished_players_count' => $game->finished_players_count,
                'last_activity_at' => $game->last_activity_at?->toIso8601String(),
                'route' => $game->locations->map(fn ($l) => [(float) $l->lat, (float) $l->lng])->values()->all(),
            ]);

        return Inertia::render('Admin/Games/Index', ['games' => $games]);
    }

    public function create()
    {
        return Inertia::render('Admin/Games/Create');
    }

    public function store(StoreGameRequest $request)
    {
        $game = $request->user()->games()->create([
            'name' => $request->validated('name'),
            'slug' => SlugGenerator::unique($request->validated('name')),
            'status' => GameStatus::Draft,
        ]);

        return redirect()->route('admin.games.edit', $game);
    }

    public function edit(Game $game)
    {
        $this->authorize('update', $game);

        $media = fn (string $path) => ['path' => $path, 'url' => Storage::disk('public')->url($path)];

        return Inertia::render('Admin/Games/Edit', [
            'game' => $game,
            'locations' => $game->locations()->get()->map(fn ($location) => [
                'id' => $location->id,
                'order' => $location->order,
                'name' => $location->name,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'arrival_radius_m' => $location->arrival_radius_m,
                'task_type' => $location->task_type->value,
                'correct_index' => $location->correctImageIndex(),
                'images' => array_map($media, $location->images()),
                'reference_image' => $location->referenceImage() ? $media($location->referenceImage()) : null,
                'password_answers' => implode("\n", $location->passwordAnswers()),
                'info_title' => $location->info_title,
                'info_text' => $location->info_text,
                'info_images' => array_map($media, $location->infoImages()),
            ])->values(),
            'play_url' => route('play.show', $game->slug),
            'players_count' => $game->playerSessions()->count(),
        ]);
    }

    public function update(UpdateGameRequest $request, Game $game)
    {
        $this->authorize('update', $game);

        $game->update($request->validated());

        return back();
    }

    public function destroy(Game $game)
    {
        $this->authorize('delete', $game);

        $game->deleteWithFiles();

        return redirect()->route('admin.games.index');
    }

    public function publish(Game $game)
    {
        $this->authorize('update', $game);

        abort_unless($game->locations()->exists(), 422);

        $game->update(['status' => GameStatus::Published]);

        return back();
    }

    public function unpublish(Game $game)
    {
        $this->authorize('update', $game);

        $game->update(['status' => GameStatus::Draft]);

        return back();
    }
}
