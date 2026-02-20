<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = ['board_column_id', 'title', 'description', 'position', 'priority', 'due_date','assignee_id'];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments()
    {
        // Traemos los comentarios ordenados del más antiguo al más nuevo
        return $this->hasMany(Comment::class)->oldest();
    }
}
