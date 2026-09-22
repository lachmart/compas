<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocationRequest;
use App\Models\Game;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function store(LocationRequest $request, Game $game)
    {
        $this->authorize('update', $game);

        $game->locations()->create([
            'lat' => $request->validated('lat'),
            'lng' => $request->validated('lng'),
            'arrival_radius_m' => $request->validated('arrival_radius_m'),
            'task_type' => $request->validated('task_type'),
            'task_payload' => $this->buildTaskPayload($request),
            'order' => $game->locations()->max('order') + 1,
        ]);

        return back();
    }

    public function update(LocationRequest $request, Game $game, Location $location)
    {
        $this->authorize('update', $game);

        $location->update([
            'lat' => $request->validated('lat'),
            'lng' => $request->validated('lng'),
            'arrival_radius_m' => $request->validated('arrival_radius_m'),
            'task_type' => $request->validated('task_type'),
            'task_payload' => $this->buildTaskPayload($request),
        ]);

        return back();
    }

    public function destroy(Game $game, Location $location)
    {
        $this->authorize('update', $game);

        $location->delete();

        return back();
    }

    public function reorder(Request $request, Game $game)
    {
        $this->authorize('update', $game);

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('locations', 'id')->where('game_id', $game->id)],
        ]);

        $order = $request->input('order');

        if (count($order) !== $game->locations()->count()) {
            abort(422, 'Order must include every location in this game.');
        }

        DB::transaction(function () use ($order, $game) {
            foreach ($order as $index => $locationId) {
                Location::where('id', $locationId)
                    ->where('game_id', $game->id)
                    ->update(['order' => $index + 1]);
            }
        });

        return back();
    }

    private function buildTaskPayload(LocationRequest $request): ?array
    {
        return match (TaskType::from($request->validated('task_type'))) {
            TaskType::ArrivalOnly => null,
            TaskType::PickImage => [
                'images' => collect($request->file('images'))
                    ->map(fn ($file) => $file->store('task-images', 'public'))
                    ->values()
                    ->all(),
                'correct_index' => (int) $request->validated('correct_index'),
            ],
            TaskType::PhotoOfSomething, TaskType::SelfieDoingSomething => [
                'reference_image' => $request->file('reference_image')->store('task-images', 'public'),
            ],
        };
    }
}
