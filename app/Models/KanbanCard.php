<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanCard extends Model
{
    protected $fillable = [
        'column_id', 'project_id', 'created_by', 'title', 'description',
        'label', 'label_color', 'due_date', 'priority', 'position',
    ];

    protected $casts = ['due_date' => 'date'];

    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'column_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            3 => 'High',
            2 => 'Medium',
            default => 'Low',
        };
    }
}
