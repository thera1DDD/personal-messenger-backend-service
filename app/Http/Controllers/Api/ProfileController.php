<?php

namespace App\Http\Controllers\Api;

use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ChatPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'max:' . config('chat.avatar_max_upload_size_kb'),
                'mimetypes:' . implode(',', config('chat.avatar_allowed_mime_types')),
            ],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $avatar */
        $avatar = $request->file('avatar');

        $disk = config('chat.avatar_disk');
        $directory = trim(config('chat.avatar_directory'), '/');
        $newPath = $avatar->storeAs(
            $directory,
            $user->username . '-' . Str::uuid() . '.' . $avatar->getClientOriginalExtension(),
            $disk
        );

        $oldDisk = $user->avatar_disk;
        $oldPath = $user->avatar_path;

        $user->forceFill([
            'avatar_disk' => $disk,
            'avatar_path' => $newPath,
        ])->save();

        if ($oldDisk && $oldPath && Storage::disk($oldDisk)->exists($oldPath)) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        broadcast(new ProfileUpdated($user->fresh()))->toOthers();

        return response()->json([
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    private function formatUser(User $user): array
    {
        return ChatPayload::user($user, includeLastSeen: true);
    }
}
