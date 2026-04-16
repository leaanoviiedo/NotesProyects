<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = ['project_id', 'user_id', 'title', 'content', 'category', 'tags', 'is_pinned'];

    protected $casts = [
        'tags'      => 'array',
        'is_pinned' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getExcerptAttribute(): string
    {
        return mb_substr(strip_tags($this->content ?? ''), 0, 120) . '...';
    }
}
