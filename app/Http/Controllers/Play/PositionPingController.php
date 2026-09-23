<?php

namespace App\Http\Controllers\Play;

use App\Events\PlayerPositionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Play\StorePositionPingRequest;
use App\Models\Game;

class PositionPingController extends Controller
{
    use ResolvesPlayerSession;

    public function store(StorePositionPingRequest $request, Game $game)
    {
        $playerSession = $this->resolvePlayerSession($game);

        $ping = $playerSession->pings()->create($request->validated());

        PlayerPositionUpdated::dispatch($ping);

        return response()->noContent();
    }
}
