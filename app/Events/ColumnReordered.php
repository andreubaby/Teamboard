<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ColumnReordered implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $projectId,
        /** @var int[] */
        public array $orderedIds,
        public int $senderId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'ColumnReordered';
    }

    public function broadcastWith(): array
    {
        return [
            'projectId'   => $this->projectId,
            'orderedIds'  => $this->orderedIds,
            'senderId'    => $this->senderId,
        ];
    }
}
