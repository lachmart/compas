<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'order', 'name', 'lat', 'lng', 'arrival_radius_m', 'task_type', 'task_payload'];

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

    public function images(): array
    {
        return $this->task_payload['images'] ?? [];
    }

    public function correctImageIndex(): ?int
    {
        return $this->task_payload['correct_index'] ?? null;
    }

    public function referenceImage(): ?string
    {
        return $this->task_payload['reference_image'] ?? null;
    }

    /** Every stored file this location's task payload references. */
    public function mediaPaths(): array
    {
        return array_values(array_filter([...$this->images(), $this->referenceImage()]));
    }
}
