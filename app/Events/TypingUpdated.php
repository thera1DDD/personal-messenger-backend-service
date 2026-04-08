<?php

namespace App\Events;

use App\Models\User;
use App\Support\ChatChannel;
use App\Support\ChatPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly User $user,
        private readonly bool $isTyping,
        private readonly string $updatedAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatChannel::dialogChannelName())];
    }

    public function broadcastAs(): string
    {
        return 'typing.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => ChatPayload::reader($this->user),
            'is_typing' => $this->isTyping,
            'updated_at' => $this->updatedAt,
            'expires_in_seconds' => config('chat.typing_ttl_seconds'),
            'server_timestamp' => now()->toIso8601String(),
        ];
    }
}
