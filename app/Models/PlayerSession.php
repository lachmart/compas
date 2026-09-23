<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerSession extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'nickname', 'current_location_index', 'started_at', 'finished_at'];

    protected $casts = [
        'current_location_index' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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
}
