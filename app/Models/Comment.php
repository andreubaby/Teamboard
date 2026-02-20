<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = ['card_id', 'user_id', 'content'];

    // Un comentario pertenece a un usuario (quien lo escribió)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Un comentario pertenece a una tarjeta
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
