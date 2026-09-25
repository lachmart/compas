<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word().' '.fake()->word().' '.fake()->word();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => GameStatus::Draft,
        ];
    }
}
