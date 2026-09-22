<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function update(User $user, Game $game): bool
    {
        return $user->id === $game->owner_id;
    }

    public function delete(User $user, Game $game): bool
    {
        return $user->id === $game->owner_id;
    }
}
