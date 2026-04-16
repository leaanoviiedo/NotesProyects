<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShareLink extends Model
{
    protected $fillable = ['token', 'project_id', 'created_by', 'can_kanban', 'can_notes', 'can_calendar', 'expires_at', 'is_active'];

    protected $casts = [
        'can_kanban'   => 'boolean',
        'can_notes'    => 'boolean',
        'can_calendar' => 'boolean',
        'is_active'    => 'boolean',
        'expires_at'   => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->token = $model->token ?? Str::random(48));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function getUrlAttribute(): string
    {
        return route('share.view', $this->token);
    }
}
