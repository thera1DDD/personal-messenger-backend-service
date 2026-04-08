<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageCreated;
use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\MediaAttachment;
use App\Models\Message;
use App\Models\User;
use App\Support\ChatPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $limit = $validated['limit'] ?? 50;

        $query = Message::query()
            ->with(['sender', 'attachments'])
            ->orderByDesc('id');

        if (! empty($validated['after_id'])) {
            $messages = Message::query()
                ->with(['sender', 'attachments'])
                ->where('id', '>', $validated['after_id'])
                ->orderBy('id')
                ->limit($limit)
                ->get();

            return response()->json([
                'messages' => ChatPayload::messageCollection($messages),
            ]);
        }

        if (! empty($validated['before_id'])) {
            $query->where('id', '<', $validated['before_id']);
        }

        $messages = $query
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'messages' => ChatPayload::messageCollection($messages),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'media_ids' => ['nullable', 'array', 'min:1', 'max:10'],
            'media_ids.*' => ['integer', Rule::exists('media_attachments', 'id')],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $mediaIds = $validated['media_ids'] ?? [];

        if ($body === '' && $mediaIds === []) {
            return response()->json([
                'message' => 'Message text or at least one attachment is required.',
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        $message = DB::transaction(function () use ($body, $mediaIds, $user) {
            $message = Message::create([
                'sender_id' => $user->id,
                'body' => $body !== '' ? $body : null,
            ]);

            if ($mediaIds !== []) {
                $attachments = MediaAttachment::query()
                    ->whereIn('id', $mediaIds)
                    ->where('uploaded_by', $user->id)
                    ->whereNull('message_id')
                    ->get();

                if ($attachments->count() !== count($mediaIds)) {
                    throw ValidationException::withMessages([
                        'media_ids' => ['Some attachments are invalid or already attached.'],
                    ]);
                }

                MediaAttachment::query()
                    ->whereIn('id', $attachments->pluck('id'))
                    ->update(['message_id' => $message->id]);
            }

            return $message->load(['sender', 'attachments']);
        });

        broadcast(new MessageCreated($message))->toOthers();

        return response()->json([
            'message' => ChatPayload::message($message),
        ], 201);
    }

    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $message->forceFill([
            'read_at' => now(),
        ])->save();

        broadcast(new MessageRead($message->fresh(), $user))->toOthers();

        return response()->json([
            'message' => ChatPayload::message($message->fresh()->load(['sender', 'attachments'])),
        ]);
    }

    public function markManyAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1', 'max:100'],
            'message_ids.*' => ['integer', Rule::exists('messages', 'id')],
        ]);

        /** @var User $user */
        $user = $request->user();
        $readAt = now();

        $messages = Message::query()
            ->with(['sender', 'attachments'])
            ->whereIn('id', $validated['message_ids'])
            ->get();

        Message::query()
            ->whereIn('id', $messages->pluck('id'))
            ->update(['read_at' => $readAt]);

        $messages = $messages->map(function (Message $message) use ($readAt) {
            $message->read_at = $readAt;

            return $message;
        });

        foreach ($messages as $message) {
            broadcast(new MessageRead($message, $user))->toOthers();
        }

        return response()->json([
            'messages' => ChatPayload::messageCollection($messages),
        ]);
    }
}
