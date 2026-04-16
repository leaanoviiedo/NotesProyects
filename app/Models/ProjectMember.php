<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    protected $fillable = ['project_id', 'user_id', 'role', 'can_kanban', 'can_notes', 'can_calendar'];

    protected $casts = [
        'can_kanban'  => 'boolean',
        'can_notes'   => 'boolean',
        'can_calendar' => 'boolean',
        'joined_at'   => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
