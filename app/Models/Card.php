<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = ['board_column_id', 'title', 'description', 'position', 'priority'];

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }
}
