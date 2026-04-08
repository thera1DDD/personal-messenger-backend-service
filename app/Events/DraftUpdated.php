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

class DraftUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly User $user,
        private readonly string $text,
        private readonly string $updatedAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatChannel::dialogChannelName())];
    }

    public function broadcastAs(): string
    {
        return 'draft.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => ChatPayload::reader($this->user),
            'text' => $this->text,
            'updated_at' => $this->updatedAt,
            'expires_in_seconds' => config('chat.draft_ttl_seconds'),
            'server_timestamp' => now()->toIso8601String(),
        ];
    }
}
