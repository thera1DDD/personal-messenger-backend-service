<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'uploaded_by',
        'disk',
        'path',
        'preview_disk',
        'preview_path',
        'thumbnail_disk',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'media_type',
        'size_bytes',
    ];

    protected $appends = [
        'url',
        'original_url',
        'preview_url',
        'thumbnail_url',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getOriginalUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (! $this->preview_disk || ! $this->preview_path) {
            return null;
        }

        return Storage::disk($this->preview_disk)->url($this->preview_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_disk || ! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->thumbnail_disk)->url($this->thumbnail_path);
    }
}
