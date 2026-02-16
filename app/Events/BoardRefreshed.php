<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// app/Events/BoardRefreshed.php
class BoardRefreshed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $project_id;

    public function __construct($project_id)
    {
        $this->project_id = $project_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('project.' . $this->project_id);
    }

    public function broadcastAs()
    {
        return 'BoardRefreshed';
    }
}
