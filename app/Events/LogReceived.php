<?php

namespace App\Events;

use App\Models\ProjectLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LogReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $projectId,
        public readonly array $log, // serialized log data
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("project.{$this->projectId}.logs"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'log.received';
    }
}
