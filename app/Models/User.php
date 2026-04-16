<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar', 'google_id', 'google_token', 'google_refresh_token', 'google_calendar_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function memberProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'can_kanban', 'can_notes', 'can_calendar'])
            ->withTimestamps();
    }

    public function allProjects()
    {
        $owned = $this->ownedProjects()->get();
        $member = $this->memberProjects()->get();
        return $owned->merge($member)->unique('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return $this->avatar;
        }
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=80";
    }

    public function savedProjects(): HasMany
    {
        return $this->hasMany(\App\Models\SavedProject::class);
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }

    /**
     * Get the user's personal project, creating one if it doesn't exist.
     * Each user has exactly one personal project.
     */
    public function getOrCreatePersonalProject(): Project
    {
        $personal = $this->ownedProjects()->where('is_personal', true)->first();

        if (!$personal) {
            $personal = Project::create([
                'name'        => 'Personal',
                'description' => 'Your personal workspace',
                'color'       => '#4f46e5',
                'icon'        => 'person',
                'owner_id'    => $this->id,
                'is_personal' => true,
                'is_favorite' => true,
            ]);
        }

        return $personal;
    }
}

