<?php

namespace App\Support;

use App\Models\PlayerSession;

/**
 * The single shape the dozor's tracking UI knows a player by — used for the
 * initial Inertia props, the JSON detail endpoint and PlayerProgressed
 * broadcasts, so the three can never drift apart.
 */
class TrackedPlayer
{
    public static function make(PlayerSession $playerSession): array
    {
        $ping = $playerSession->latestPing;
        $game = $playerSession->game;

        return [
            'id' => $playerSession->id,
            'nickname' => $playerSession->nickname,
            'color' => $playerSession->color(),
            'current_location_index' => $playerSession->current_location_index,
            'locations_count' => $game->locations_count ?? $game->locations()->count(),
            'finished_at' => $playerSession->finished_at?->toIso8601String(),
            'last_seen_at' => ($playerSession->last_seen_at ?? $playerSession->started_at)->toIso8601String(),
            'position' => $ping ? [
                'lat' => (float) $ping->lat,
                'lng' => (float) $ping->lng,
                'heading' => $ping->heading === null ? null : (float) $ping->heading,
            ] : null,
        ];
    }
}
