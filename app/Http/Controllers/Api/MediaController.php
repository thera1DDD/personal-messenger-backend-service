<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Support\ChatPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'media_type_hint' => ['nullable', 'string', 'in:image,video,audio'],
            'files.*' => [
                'required',
                'file',
                'max:' . config('chat.max_upload_size_kb'),
                'mimetypes:' . implode(',', config('chat.allowed_mime_types')),
            ],
        ]);

        /** @var User $user */
        $user = $request->user();
        $disk = config('chat.media_disk');
        $directory = trim(config('chat.media_directory'), '/');
        $mediaTypeHint = $request->string('media_type_hint')->toString();

        $uploadedFiles = [];

        foreach ($request->file('files', []) as $file) {
            /** @var UploadedFile $file */
            $path = $file->storeAs(
                $directory . '/' . now()->format('Y/m'),
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                $disk
            );

            $attachment = MediaAttachment::create([
                'uploaded_by' => $user->id,
                'disk' => $disk,
                'path' => $path,
                'preview_disk' => null,
                'preview_path' => null,
                'thumbnail_disk' => null,
                'thumbnail_path' => null,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'media_type' => $this->detectMediaType($file, $mediaTypeHint !== '' ? $mediaTypeHint : null),
                'size_bytes' => $file->getSize(),
            ]);

            $uploadedFiles[] = $this->formatAttachment($attachment);
        }

        return response()->json([
            'files' => $uploadedFiles,
        ], 201);
    }

    private function formatAttachment(MediaAttachment $attachment): array
    {
        return array_merge(
            ChatPayload::attachment($attachment),
            [
                'message_id' => $attachment->message_id,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ]
        );
    }

    private function detectMediaType(UploadedFile $file, ?string $mediaTypeHint = null): string
    {
        if (in_array($mediaTypeHint, ['image', 'video', 'audio'], true)) {
            return $mediaTypeHint;
        }

        $detectedMimeType = (string) $file->getMimeType();
        $clientMimeType = (string) $file->getClientMimeType();
        $extension = Str::lower($file->getClientOriginalExtension());

        if (Str::startsWith($detectedMimeType, 'audio/') || Str::startsWith($clientMimeType, 'audio/')) {
            return 'audio';
        }

        if (in_array($extension, ['mp3', 'wav', 'aac', 'm4a', 'ogg', 'oga', 'opus', 'flac'], true)) {
            return 'audio';
        }

        if ($extension === 'webm' && ! Str::startsWith($clientMimeType, 'video/')) {
            return 'audio';
        }

        if (Str::startsWith($detectedMimeType, 'video/') || Str::startsWith($clientMimeType, 'video/')) {
            return 'video';
        }

        return 'image';
    }
}
