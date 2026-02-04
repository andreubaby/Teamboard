<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class CardMoved implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $projectId,
        public int $cardId,
        public int $fromColumnId,
        public int $toColumnId,
        public int $toPosition,
        public int $senderId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'CardMoved';
    }

    public function broadcastWith(): array
    {
        return [
            'projectId'    => $this->projectId,
            'cardId'       => $this->cardId,
            'fromColumnId' => $this->fromColumnId,
            'toColumnId'   => $this->toColumnId,
            'toPosition'   => $this->toPosition,
            'senderId'     => $this->senderId,
        ];
    }
}
