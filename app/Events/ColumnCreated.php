<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ColumnCreated implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $projectId,
        public array $column,
        public int $senderId,
    ) {
        $this->dontBroadcastToCurrentUser();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'ColumnCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'projectId' => $this->projectId,
            'column'    => $this->column,
            'senderId'  => $this->senderId,
        ];
    }
}
