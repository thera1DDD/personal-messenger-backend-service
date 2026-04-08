<?php

namespace App\Events;

use App\Models\User;
use App\Support\ChatChannel;
use App\Support\ChatPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(private readonly User $user)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatChannel::dialogChannelName())];
    }

    public function broadcastAs(): string
    {
        return 'profile.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => ChatPayload::user($this->user),
            'server_timestamp' => now()->toIso8601String(),
        ];
    }
}
