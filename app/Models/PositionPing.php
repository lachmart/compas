<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionPing extends Model
{
    use HasFactory;

    protected $fillable = ['player_session_id', 'lat', 'lng', 'heading'];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'heading' => 'decimal:1',
    ];

    public function playerSession(): BelongsTo
    {
        return $this->belongsTo(PlayerSession::class);
    }
}
