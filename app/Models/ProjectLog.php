<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectLog extends Model
{
    public const UPDATED_AT = null; // single-timestamp table

    protected $fillable = [
        'project_id',
        'level',
        'channel',
        'message',
        'stack_trace',
        'context',
        'source_app',
        'environment',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // Convenience: CSS class for the level badge
    public function getLevelColorAttribute(): string
    {
        return match ($this->level) {
            'error'   => 'text-red-400 bg-red-500/10 border-red-500/20',
            'warning' => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
            'info'    => 'text-sky-400 bg-sky-500/10 border-sky-500/20',
            'debug'   => 'text-slate-400 bg-slate-500/10 border-slate-500/20',
            default   => 'text-slate-400 bg-slate-500/10 border-slate-500/20',
        };
    }
}
