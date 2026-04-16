<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snippet extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'title', 'description',
        'language', 'code', 'tags', 'is_favorite',
    ];

    protected $casts = [
        'tags'        => 'array',
        'is_favorite' => 'boolean',
    ];

    // Map language slug → file extension for export
    public static array $extensions = [
        'php'        => 'php',
        'go'         => 'go',
        'javascript' => 'js',
        'typescript' => 'ts',
        'python'     => 'py',
        'sql'        => 'sql',
        'bash'       => 'sh',
        'dockerfile' => 'Dockerfile',
        'html'       => 'html',
        'css'        => 'css',
        'json'       => 'json',
        'yaml'       => 'yaml',
        'plaintext'  => 'txt',
    ];

    public function getExtensionAttribute(): string
    {
        return self::$extensions[$this->language] ?? 'txt';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
