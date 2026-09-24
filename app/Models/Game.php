<?php

namespace App\Models;

use App\Enums\Avatar;
use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function playerSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PlayerSession::class);
    }

    /**
     * Animals held by players still in the game (not hidden, not finished,
     * seen within PlayerSession::INACTIVE_AFTER_MINUTES), so two children
     * never pick the same one. When all are held, none count as taken —
     * a ninth child must still be able to join.
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
