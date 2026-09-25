<?php

namespace App\Models;

use App\Enums\Avatar;
use App\Enums\GameStatus;
use App\Support\AttemptPhotos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Game extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name', 'slug', 'status'];

    protected $casts = [
        'status' => GameStatus::class,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class)->orderBy('order')->orderBy('id');
    }

    public function playerSessions(): HasMany
    {
        return $this->hasMany(PlayerSession::class);
    }

    /**
     * Deletes the game (locations, players, attempts and pings cascade) and
     * then its files: the dozor's task images and the players' photos.
     */
    public function deleteWithFiles(): void
    {
        $taskImages = $this->locations->flatMap->mediaPaths();
        $photos = LocationAttempt::whereIn('player_session_id', $this->playerSessions()->pluck('id'))
            ->whereNotNull('submitted_photo_path')
            ->pluck('submitted_photo_path');

        $this->delete();

        Storage::disk('public')->delete($taskImages->all());
        AttemptPhotos::delete($photos);
    }

    /**
     * Animals held by players still in the game (not hidden, not finished,
     * seen within PlayerSession::INACTIVE_AFTER_MINUTES), so two players
     * never pick the same one. When all are held, none count as taken —
     * a ninth player must still be able to join.
     *
     * @return list<string>
     */
    public function takenAvatars(): array
    {
        $taken = $this->playerSessions()
            ->active()
            ->whereNotNull('avatar')
            ->distinct()
            ->pluck('avatar')
            ->map(fn (Avatar $avatar) => $avatar->value)
            ->sort()
            ->values()
            ->all();

        return count($taken) >= count(Avatar::cases()) ? [] : $taken;
    }
}
