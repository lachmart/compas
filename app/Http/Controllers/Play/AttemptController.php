<?php

namespace App\Http\Controllers\Play;

use App\Enums\AttemptStatus;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Play\SubmitAnswerRequest;
use App\Http\Requests\Play\SubmitPhotoRequest;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use Illuminate\Support\Facades\Storage;

class AttemptController extends Controller
{
    use ResolvesPlayerSession;

    public function arrive(Game $game, Location $location)
    {
        $playerSession = $this->resolvePlayerSession($game);
        $this->ensureCurrentTarget($playerSession, $location);

        $attempt = LocationAttempt::firstOrCreate(
            ['player_session_id' => $playerSession->id, 'location_id' => $location->id],
            ['status' => AttemptStatus::Pending],
        );

        if ($attempt->status === AttemptStatus::Pending) {
            $attempt->update(['status' => AttemptStatus::Arrived, 'arrived_at' => now()]);
        }

        if ($location->task_type === TaskType::ArrivalOnly) {
            $this->completeAttempt($attempt, $playerSession);
        }

        return response()->json([
            'attempt_status' => $attempt->fresh()->status->value,
            'task_type' => $location->task_type->value,
            'images' => $location->task_type === TaskType::PickImage
                ? collect($location->images())->map(fn ($path) => Storage::disk('public')->url($path))->values()->all()
                : null,
            'reference_image' => in_array($location->task_type, [TaskType::PhotoOfSomething, TaskType::SelfieDoingSomething]) && $location->referenceImage()
                ? Storage::disk('public')->url($location->referenceImage())
                : null,
            'player' => $this->playerState($playerSession->fresh()),
        ]);
    }

    public function answer(SubmitAnswerRequest $request, Game $game, Location $location)
    {
        $playerSession = $this->resolvePlayerSession($game);
        $this->ensureCurrentTarget($playerSession, $location);

        abort_unless($location->task_type === TaskType::PickImage, 422);

        $attempt = LocationAttempt::where('player_session_id', $playerSession->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $correct = $request->validated('selected_index') === $location->correctImageIndex();

        if ($correct) {
            $this->completeAttempt($attempt, $playerSession);
        }

        return response()->json([
            'correct' => $correct,
            'player' => $this->playerState($playerSession->fresh()),
        ]);
    }

    public function photo(SubmitPhotoRequest $request, Game $game, Location $location)
    {
        $playerSession = $this->resolvePlayerSession($game);
        $this->ensureCurrentTarget($playerSession, $location);

        abort_unless(
            in_array($location->task_type, [TaskType::PhotoOfSomething, TaskType::SelfieDoingSomething]),
            422,
        );

        $attempt = LocationAttempt::where('player_session_id', $playerSession->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $path = $request->file('photo')->store('attempt-photos', 'public');
        $attempt->update(['submitted_photo_path' => $path]);

        $this->completeAttempt($attempt, $playerSession);

        return response()->json([
            'player' => $this->playerState($playerSession->fresh()),
        ]);
    }

    private function completeAttempt(LocationAttempt $attempt, PlayerSession $playerSession): void
    {
        $attempt->update(['status' => AttemptStatus::Completed, 'completed_at' => now()]);
        $playerSession->advance();
    }

    private function ensureCurrentTarget(PlayerSession $playerSession, Location $location): void
    {
        $current = $playerSession->currentLocation();
        abort_if($current === null || $current->id !== $location->id, 403);
    }

    private function playerState(PlayerSession $playerSession): array
    {
        return [
            'current_location_index' => $playerSession->current_location_index,
            'finished_at' => $playerSession->finished_at,
        ];
    }
}
