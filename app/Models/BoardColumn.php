<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardColumn extends Model
{
    protected $table = 'board_columns';

    protected $fillable = ['project_id', 'name', 'position'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'board_column_id')->orderBy('position');
    }
}
