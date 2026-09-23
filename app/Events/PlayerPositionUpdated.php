<?php

namespace App\Events;

use App\Models\PositionPing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerPositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PositionPing $ping) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('games.'.$this->ping->playerSession->game_id.'.tracking');
    }

    public function broadcastAs(): string
    {
        return 'player.position';
    }

    public function broadcastWith(): array
    {
        return [
            'player_session_id' => $this->ping->player_session_id,
            'lat' => (float) $this->ping->lat,
            'lng' => (float) $this->ping->lng,
            'heading' => $this->ping->heading === null ? null : (float) $this->ping->heading,
            'last_seen_at' => $this->ping->created_at->toIso8601String(),
        ];
    }
}
