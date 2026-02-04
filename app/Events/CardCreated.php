<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class CardCreated implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $projectId,
        public array $card,
        public int $columnId,
        public int $senderId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'CardCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'projectId' => $this->projectId,
            'columnId'  => $this->columnId,
            'card'      => $this->card,
            'senderId'  => $this->senderId,
        ];
    }
}
