<?php

namespace Database\Factories;

use App\Models\PlayerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionPingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_session_id' => PlayerSession::factory(),
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'heading' => $this->faker->numberBetween(0, 359),
        ];
    }
}
