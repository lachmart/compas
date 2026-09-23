<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_session_id', 'location_id', 'status',
        'arrived_at', 'completed_at', 'submitted_photo_path', 'flagged_invalid',
    ];

    protected $casts = [
        'status' => AttemptStatus::class,
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'flagged_invalid' => 'boolean',
    ];

    public function playerSession(): BelongsTo
    {
        return $this->belongsTo(PlayerSession::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
