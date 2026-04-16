<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedProject extends Model
{
    protected $fillable = ['user_id', 'project_id', 'share_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(ShareLink::class, 'share_token', 'token');
    }
}
