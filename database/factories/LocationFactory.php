<?php

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'order' => 1,
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'arrival_radius_m' => 15,
            'task_type' => TaskType::ArrivalOnly,
            'task_payload' => null,
        ];
    }
}
