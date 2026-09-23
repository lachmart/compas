<?php

namespace App\Models;

use App\Enums\Avatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlayerSession extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'nickname', 'avatar', 'current_location_index', 'started_at', 'finished_at', 'last_seen_at'];

    protected $casts = [
        'avatar' => Avatar::class,
        'current_location_index' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LocationAttempt::class);
    }

    public function pings(): HasMany
    {
        return $this->hasMany(PositionPing::class);
    }

    public function currentLocation(): ?Location
    {
        return $this->game->locations()->get()->values()->get($this->current_location_index);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function advance(): void
    {
        $nextIndex = $this->current_location_index + 1;

        if ($nextIndex >= $this->game->locations()->count()) {
            $this->update(['finished_at' => now()]);

            return;
        }

        $this->update(['current_location_index' => $nextIndex]);
    }

    private const COLORS = [
        '#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4',
        '#42d4f4', '#f032e6', '#9a6324', '#800000', '#000075',
    ];

    public function latestPing(): HasOne
    {
        return $this->hasOne(PositionPing::class)->latestOfMany();
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function color(): string
    {
        return self::COLORS[$this->id % count(self::COLORS)];
    }
}
