<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $projectId;
    public $cardId;

    public function __construct($projectId, $cardId)
    {
        $this->projectId = $projectId;
        $this->cardId = $cardId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->projectId),
        ];
    }

    // 👇 ESTO ES LO QUE FALTABA 👇
    public function broadcastAs(): string
    {
        return 'CardDeleted';
    }
    // 👆 SIN ESTO, EL FRONTEND NO ESCUCHA NADA 👆

    public function broadcastWith()
    {
        return [
            'cardId' => $this->cardId,
        ];
    }
}
