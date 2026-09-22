<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameRequest;
use App\Http\Requests\Admin\UpdateGameRequest;
use App\Models\Game;
use App\Support\SlugGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Games/Index', [
            'games' => $request->user()->games()->latest()->get(),
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

        return Inertia::render('Admin/Games/Edit', [
            'game' => $game->load('locations'),
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

        $game->delete();

        return redirect()->route('admin.games.index');
    }

    public function publish(Game $game)
    {
        $this->authorize('update', $game);

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
