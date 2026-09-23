<?php

namespace App\Http\Controllers\Play;

use App\Http\Controllers\Controller;
use App\Http\Requests\Play\StorePositionPingRequest;
use App\Models\Game;

class PositionPingController extends Controller
{
    use ResolvesPlayerSession;

    public function store(StorePositionPingRequest $request, Game $game)
    {
        $playerSession = $this->resolvePlayerSession($game);

        $playerSession->pings()->create($request->validated());

        return response()->noContent();
    }
}
