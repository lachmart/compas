<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('games.{game}.tracking', function (User $user, Game $game) {
    return $user->can('view', $game);
});
