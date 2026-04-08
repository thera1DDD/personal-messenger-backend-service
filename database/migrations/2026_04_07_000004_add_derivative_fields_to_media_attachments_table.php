<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_attachments', function (Blueprint $table) {
            $table->string('preview_disk')->nullable()->after('path');
            $table->string('preview_path')->nullable()->after('preview_disk');
            $table->string('thumbnail_disk')->nullable()->after('preview_path');
            $table->string('thumbnail_path')->nullable()->after('thumbnail_disk');
        });
    }

    public function down(): void
    {
        Schema::table('media_attachments', function (Blueprint $table) {
            $table->dropColumn([
                'preview_disk',
                'preview_path',
                'thumbnail_disk',
                'thumbnail_path',
            ]);
        });
    }
};
