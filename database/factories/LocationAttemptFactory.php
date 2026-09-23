<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\PlayerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_session_id' => PlayerSession::factory(),
            'location_id' => Location::factory(),
            'status' => 'pending',
        ];
    }
}
