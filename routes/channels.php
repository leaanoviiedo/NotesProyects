<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel for kanban — returns user info for "who's here" tracking
Broadcast::channel('project.{projectId}.kanban', function ($user, int $projectId) {
    $project = Project::find($projectId);
    if (!$project || !$project->isMember($user)) {
        return false;
    }
    return ['id' => $user->id, 'name' => $user->name, 'initials' => $user->initials, 'avatar' => $user->avatar_url];
});

// Presence channel for notes
Broadcast::channel('project.{projectId}.notes', function ($user, int $projectId) {
    $project = Project::find($projectId);
    if (!$project || !$project->isMember($user)) {
        return false;
    }
    return ['id' => $user->id, 'name' => $user->name, 'initials' => $user->initials, 'avatar' => $user->avatar_url];
});

