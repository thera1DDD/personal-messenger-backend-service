<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ChatPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'in:tagir,suri'],
            'secret_code' => ['required', 'string', 'min:4', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $account = config('chat.accounts.' . $credentials['username']);

        if (! $account || ! hash_equals((string) $account['secret_code'], $credentials['secret_code'])) {
            throw ValidationException::withMessages([
                'secret_code' => ['Incorrect secret code.'],
            ]);
        }

        $user = User::where('username', $credentials['username'])->firstOrFail();

        $user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $user->tokens()->delete();

        $token = $user->createToken($credentials['device_name'] ?? 'personal-chat')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return ChatPayload::user($user, includeLastSeen: true);
    }
}
