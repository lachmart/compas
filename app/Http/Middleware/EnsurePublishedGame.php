<?php

namespace App\Http\Middleware;

use App\Enums\GameStatus;
use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublishedGame
{
    /**
     * Abort with a 404 unless the route-bound game is published.
     *
     * Runs after route model binding (SubstituteBindings is part of the
     * global 'web' middleware group, which always executes before a route
     * group's own middleware), so `{game}` is already a resolved model here.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');

        abort_unless($game instanceof Game && $game->status === GameStatus::Published, 404);

        return $next($request);
    }
}
