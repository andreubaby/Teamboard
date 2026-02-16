<?php

namespace App\Events;

use App\Models\Card;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $projectId,
        public $card, // Can be Card model or array
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'CardUpdated';
    }

    public function broadcastWith(): array
    {
        return ['card' => $this->card];
    }
}
