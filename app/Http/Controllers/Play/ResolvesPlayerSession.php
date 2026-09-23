<?php

namespace App\Http\Controllers\Play;

use App\Models\Game;
use App\Models\PlayerSession;

trait ResolvesPlayerSession
{
    protected function resolvePlayerSession(Game $game): PlayerSession
    {
        $id = session('player_session_id');
        abort_unless($id, 403);

        $playerSession = PlayerSession::find($id);
        abort_unless($playerSession && $playerSession->game_id === $game->id, 403);

        return $playerSession;
    }

    protected function findActivePlayerSession(Game $game): ?PlayerSession
    {
        $id = session('player_session_id');

        if (! $id) {
            return null;
        }

        $playerSession = PlayerSession::find($id);

        return ($playerSession && $playerSession->game_id === $game->id) ? $playerSession : null;
    }
}
