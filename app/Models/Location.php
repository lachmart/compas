<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'order', 'lat', 'lng', 'arrival_radius_m', 'task_type', 'task_payload'];

    protected $casts = [
        'task_type' => TaskType::class,
        'task_payload' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'order' => 'integer',
        'arrival_radius_m' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
