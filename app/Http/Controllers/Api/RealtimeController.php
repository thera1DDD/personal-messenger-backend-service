<?php

namespace App\Http\Controllers\Api;

use App\Events\DraftUpdated;
use App\Events\TypingUpdated;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function updateTyping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $updatedAt = now()->toIso8601String();

        broadcast(new TypingUpdated($user, $validated['is_typing'], $updatedAt))->toOthers();

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt,
        ]);
    }

    public function updateDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $updatedAt = now()->toIso8601String();

        broadcast(new DraftUpdated($user, (string) ($validated['text'] ?? ''), $updatedAt))->toOthers();

        return response()->json([
            'status' => 'ok',
            'updated_at' => $updatedAt,
        ]);
    }
}
