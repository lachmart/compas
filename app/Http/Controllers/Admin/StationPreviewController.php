<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Location;
use App\Support\PasswordAnswer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A station as the player sees it on arrival (text board, then the task),
 * so the dozor can check the pictures before the game. Nothing is recorded.
 */
class StationPreviewController extends Controller
{
    public function show(Game $game, Location $location)
    {
        $this->authorize('update', $game);

        $ids = $game->locations()->pluck('id');
        $index = $ids->search($location->id);

        return Inertia::render('Admin/Games/StationPreview', [
            'game' => ['id' => $game->id, 'name' => $game->name, 'slug' => $game->slug],
            'position' => $index + 1,
            'total' => $ids->count(),
            'previous_id' => $ids->get($index - 1),
            'next_id' => $ids->get($index + 1),
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'task_type' => $location->task_type->value,
                'info' => $location->infoBoard(),
                'images' => $location->task_type === TaskType::PickImage ? Location::publicUrls($location->images()) : null,
                'correct_index' => $location->correctImageIndex(),
                'reference_image' => $location->task_type->isPhoto() && $location->referenceImage()
                    ? Location::publicUrls([$location->referenceImage()])[0]
                    : null,
            ],
        ]);
    }

    /** Checks a password the same way the game does. */
    public function password(Request $request, Game $game, Location $location)
    {
        $this->authorize('update', $game);

        $answer = $request->validate(['answer' => ['required', 'string', 'max:200']])['answer'];

        return response()->json([
            'correct' => PasswordAnswer::matches($answer, $location->passwordAnswers()),
        ]);
    }
}
