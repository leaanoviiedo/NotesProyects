<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $projectId,
        public readonly string $action, // created | updated | deleted
        public readonly array $payload,
        public readonly int $userId,
        public readonly string $userName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("project.{$this->projectId}.notes"),
            new Channel("project.{$this->projectId}.public"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'note.updated';
    }
}
