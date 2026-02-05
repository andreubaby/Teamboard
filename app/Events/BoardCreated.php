<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class BoardCreated implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $ownerId,
        public array $project,
        public int $senderId,
    ) {
        // ✅ evita duplicar en el que crea el board (si tu UI ya lo mete por response)
        $this->dontBroadcastToCurrentUser();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("boards.{$this->ownerId}")];
    }

    public function broadcastAs(): string
    {
        return 'BoardCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'project'  => $this->project,
            'senderId' => $this->senderId,
        ];
    }
}
