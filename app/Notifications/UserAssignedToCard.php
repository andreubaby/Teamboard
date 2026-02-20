<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\Card;
use App\Models\User;

class UserAssignedToCard extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $card;
    public $assigner;

    public function __construct(Card $card, User $assigner)
    {
        $this->card = $card;
        $this->assigner = $assigner;
    }

    // Le decimos que guarde en la BD y lo emita en tiempo real
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    // Lo que se guarda en la Base de Datos
    public function toArray(object $notifiable): array
    {
        return [
            'card_id' => $this->card->id,
            'title' => $this->card->title,
            'message' => $this->assigner->name . ' te ha asignado a: ' . $this->card->title,
            'assigner_avatar' => $this->assigner->avatar_url,
        ];
    }

    // Lo que viaja instantáneamente por el WebSocket (Reverb)
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'card_id' => $this->card->id,
            'title' => $this->card->title,
            'message' => $this->assigner->name . ' te ha asignado a: ' . $this->card->title,
            'assigner_avatar' => $this->assigner->avatar_url,
        ]);
    }
}
