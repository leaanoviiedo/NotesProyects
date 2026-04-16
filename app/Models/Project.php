<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'color', 'icon', 'owner_id', 'is_archived', 'is_personal', 'is_favorite', 'links'];

    protected $casts = ['is_archived' => 'boolean', 'is_personal' => 'boolean', 'is_favorite' => 'boolean', 'links' => 'array'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'can_kanban', 'can_notes', 'can_calendar', 'joined_at'])
            ->withTimestamps();
    }

    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    public function kanbanColumns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class)->orderBy('position');
    }

    public function kanbanCards(): HasMany
    {
        return $this->hasMany(KanbanCard::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->orderByDesc('is_pinned')->orderByDesc('updated_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest()->limit(50);
    }

    public function isMember(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->members()->where('user_id', $user->id)->exists();
    }
}
