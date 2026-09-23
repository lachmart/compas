<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameRequest;
use App\Http\Requests\Admin\UpdateGameRequest;
use App\Models\Game;
use App\Models\LocationAttempt;
use App\Support\SlugGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Games/Index', [
            'games' => $request->user()->games()->withCount('locations')->latest()->get(),
        ]);
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
            ])->values(),
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

        $taskImages = $game->locations->flatMap->mediaPaths();
        $attemptPhotos = LocationAttempt::whereIn('player_session_id', $game->playerSessions()->pluck('id'))
            ->whereNotNull('submitted_photo_path')
            ->pluck('submitted_photo_path');

        $game->delete();

        Storage::disk('public')->delete([...$taskImages, ...$attemptPhotos]);

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
