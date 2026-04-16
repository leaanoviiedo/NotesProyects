<?php

namespace App\Events;

use App\Models\KanbanCard;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KanbanUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $projectId,
        public readonly string $action, // card.created | card.moved | card.updated | card.deleted | column.created
        public readonly array $payload,
        public readonly int $userId,
        public readonly string $userName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("project.{$this->projectId}.kanban"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kanban.updated';
    }
}
