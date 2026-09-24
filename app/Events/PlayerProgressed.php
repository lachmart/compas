<?php

namespace App\Events;

use App\Models\PlayerSession;
use App\Support\TrackedPlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // If the player is deleted while this broadcast waits in the queue,
    // unserializing $playerSession throws ModelNotFoundException; this makes
    // the queue drop the job quietly instead of retrying/failing forever.
    public $deleteWhenMissingModels = true;

    /**
     * @param  'joined'|'completed_location'|'finished'  $kind
     */
    public function __construct(public PlayerSession $playerSession, public string $kind) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('games.'.$this->playerSession->game_id.'.tracking');
    }

    public function broadcastAs(): string
    {
        return 'player.progressed';
    }

    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'player' => TrackedPlayer::make($this->playerSession->fresh()),
        ];
    }
}
