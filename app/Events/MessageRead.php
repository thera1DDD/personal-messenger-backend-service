<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use App\Support\ChatChannel;
use App\Support\ChatPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        private readonly Message $message,
        private readonly User $reader
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatChannel::dialogChannelName())];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'read_at' => optional($this->message->read_at)?->toIso8601String(),
            'reader' => ChatPayload::reader($this->reader),
            'server_timestamp' => now()->toIso8601String(),
        ];
    }
}
