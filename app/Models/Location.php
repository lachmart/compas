<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'order', 'name', 'lat', 'lng', 'arrival_radius_m', 'task_type', 'task_payload', 'info_title', 'info_text', 'info_images'];

    protected $casts = [
        'task_type' => TaskType::class,
        'task_payload' => 'array',
        'info_images' => 'array',
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

    /** Accepted answers of a password task, as the dozor typed them. */
    public function passwordAnswers(): array
    {
        return $this->task_payload['answers'] ?? [];
    }

    public function infoImages(): array
    {
        return $this->info_images ?? [];
    }

    /** Whether the player gets a text/picture board on arrival. */
    public function hasInfo(): bool
    {
        return filled($this->info_title) || filled($this->info_text) || $this->infoImages() !== [];
    }

    /**
     * The text board as the player sees it (public picture URLs), or null
     * when there is none.
     */
    public function infoBoard(): ?array
    {
        if (! $this->hasInfo()) {
            return null;
        }

        return [
            'title' => $this->info_title,
            'text' => $this->info_text,
            'images' => self::publicUrls($this->infoImages()),
        ];
    }

    /** @param  list<string>  $paths */
    public static function publicUrls(array $paths): array
    {
        return array_map(fn (string $path) => Storage::disk('public')->url($path), array_values($paths));
    }

    /** Every stored file this location references (task and info board). */
    public function mediaPaths(): array
    {
        return array_values(array_filter([...$this->images(), $this->referenceImage(), ...$this->infoImages()]));
    }
}
