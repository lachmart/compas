<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'nickname' => $this->faker->firstName(),
            'avatar' => 'fox',
            'current_location_index' => 0,
            'started_at' => now(),
            'finished_at' => null,
            'last_seen_at' => now(),
        ];
    }
}
