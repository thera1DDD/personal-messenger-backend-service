<?php

namespace App\Support;

use App\Models\MediaAttachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class ChatPayload
{
    public static function user(User $user, bool $includeLastSeen = false): array
    {
        $payload = [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'avatar_color' => $user->avatar_color,
            'avatar_url' => $user->avatar_url,
        ];

        if ($includeLastSeen) {
            $payload['last_seen_at'] = optional($user->last_seen_at)?->toIso8601String();
        }

        return $payload;
    }

    public static function reader(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
        ];
    }

    public static function attachment(MediaAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'media_type' => $attachment->media_type,
            'size_bytes' => $attachment->size_bytes,
            'url' => $attachment->url,
            'original_url' => $attachment->original_url,
            'preview_url' => $attachment->preview_url,
            'thumbnail_url' => $attachment->thumbnail_url,
        ];
    }

    public static function message(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'read_at' => optional($message->read_at)?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'sender' => self::user($message->sender),
            'attachments' => $message->attachments
                ->map(fn (MediaAttachment $attachment) => self::attachment($attachment))
                ->values()
                ->all(),
        ];
    }

    public static function messageCollection(Collection $messages): array
    {
        return $messages
            ->map(fn (Message $message) => self::message($message))
            ->values()
            ->all();
    }
}
