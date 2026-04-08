<?php

namespace App\Events;

use App\Models\Message;
use App\Support\ChatChannel;
use App\Support\ChatPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(private readonly Message $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatChannel::dialogChannelName())];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => ChatPayload::message($this->message),
            'server_timestamp' => now()->toIso8601String(),
        ];
    }
}
