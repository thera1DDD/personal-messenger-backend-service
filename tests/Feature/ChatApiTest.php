<?php

namespace Tests\Feature;

use App\Models\MediaAttachment;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\ChatUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chat.accounts.tagir.secret_code', '1111');
        config()->set('chat.accounts.suri.secret_code', '2222');

        $this->seed(ChatUsersSeeder::class);
    }

    public function test_user_can_login_with_secret_code(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'tagir',
            'secret_code' => '1111',
            'device_name' => 'test-device',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.username', 'tagir')
            ->assertJsonPath('user.avatar_url', null)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_user_can_update_avatar(): void
    {
        Storage::fake('public');

        $user = User::where('username', 'tagir')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 300, 300),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.username', 'tagir');

        $updatedUser = $user->fresh();

        $this->assertNotNull($updatedUser->avatar_path);
        $this->assertNotNull($updatedUser->avatar_url);
        Storage::disk('public')->assertExists($updatedUser->avatar_path);
    }

    public function test_updating_avatar_replaces_previous_file(): void
    {
        Storage::fake('public');

        $user = User::where('username', 'tagir')->firstOrFail();

        $this->actingAs($user, 'sanctum')->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('first.png', 300, 300),
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $firstPath = $user->fresh()->avatar_path;

        $this->actingAs($user->fresh(), 'sanctum')->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('second.png', 300, 300),
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $secondPath = $user->fresh()->avatar_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_user_can_upload_media_and_send_message(): void
    {
        Storage::fake('public');

        $user = User::where('username', 'tagir')->firstOrFail();

        $uploadResponse = $this->actingAs($user, 'sanctum')->postJson('/api/media', [
            'files' => [
                UploadedFile::fake()->image('photo.png'),
                UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
            ],
        ]);

        $uploadResponse->assertCreated()->assertJsonCount(2, 'files');

        $mediaIds = collect($uploadResponse->json('files'))->pluck('id')->all();

        $messageResponse = $this->actingAs($user, 'sanctum')->postJson('/api/messages', [
            'body' => 'hello',
            'media_ids' => $mediaIds,
        ]);

        $messageResponse
            ->assertCreated()
            ->assertJsonPath('message.body', 'hello')
            ->assertJsonCount(2, 'message.attachments')
            ->assertJsonPath('message.attachments.0.url', $messageResponse->json('message.attachments.0.original_url'))
            ->assertJsonPath('message.attachments.0.preview_url', null)
            ->assertJsonPath('message.attachments.0.thumbnail_url', null);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $user->id,
            'body' => 'hello',
        ]);

        $this->assertSame(2, MediaAttachment::whereNotNull('message_id')->count());
    }

    public function test_user_can_upload_audio_attachment(): void
    {
        Storage::fake('public');

        $user = User::where('username', 'tagir')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/media', [
            'files' => [
                UploadedFile::fake()->create('voice.webm', 512, 'audio/webm'),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('files.0.media_type', 'audio')
            ->assertJsonPath('files.0.mime_type', 'audio/webm')
            ->assertJsonPath('files.0.url', $response->json('files.0.original_url'))
            ->assertJsonPath('files.0.preview_url', null)
            ->assertJsonPath('files.0.thumbnail_url', null);
    }

    public function test_user_can_force_webm_upload_to_be_treated_as_audio(): void
    {
        Storage::fake('public');

        $user = User::where('username', 'tagir')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')->post('/api/media', [
            'media_type_hint' => 'audio',
            'files' => [
                UploadedFile::fake()->create('voice.webm', 512, 'video/webm'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('files.0.media_type', 'audio');
    }

    public function test_messages_endpoint_returns_chat_history(): void
    {
        $tagir = User::where('username', 'tagir')->firstOrFail();
        $suri = User::where('username', 'suri')->firstOrFail();

        Message::create([
            'sender_id' => $tagir->id,
            'body' => 'First',
        ]);

        Message::create([
            'sender_id' => $suri->id,
            'body' => 'Second',
        ]);

        $response = $this->actingAs($tagir, 'sanctum')->getJson('/api/messages');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.body', 'First')
            ->assertJsonPath('messages.1.body', 'Second')
            ->assertJsonPath('messages.0.sender.avatar_url', null);
    }

    public function test_messages_endpoint_supports_after_id_for_realtime_recovery(): void
    {
        $tagir = User::where('username', 'tagir')->firstOrFail();

        $first = Message::create([
            'sender_id' => $tagir->id,
            'body' => 'First',
        ]);

        Message::create([
            'sender_id' => $tagir->id,
            'body' => 'Second',
        ]);

        Message::create([
            'sender_id' => $tagir->id,
            'body' => 'Third',
        ]);

        $response = $this->actingAs($tagir, 'sanctum')->getJson('/api/messages?after_id=' . $first->id . '&limit=10');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.body', 'Second')
            ->assertJsonPath('messages.1.body', 'Third');
    }

    public function test_user_can_mark_many_messages_as_read(): void
    {
        $tagir = User::where('username', 'tagir')->firstOrFail();
        $suri = User::where('username', 'suri')->firstOrFail();

        $first = Message::create([
            'sender_id' => $suri->id,
            'body' => 'First',
        ]);

        $second = Message::create([
            'sender_id' => $suri->id,
            'body' => 'Second',
        ]);

        $response = $this->actingAs($tagir, 'sanctum')->postJson('/api/messages/read', [
            'message_ids' => [$first->id, $second->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'messages');

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }

    public function test_user_can_send_typing_and_draft_updates(): void
    {
        $user = User::where('username', 'tagir')->firstOrFail();

        $this->actingAs($user, 'sanctum')->postJson('/api/realtime/typing', [
            'is_typing' => true,
        ])->assertOk();

        $this->actingAs($user, 'sanctum')->postJson('/api/realtime/draft', [
            'text' => 'hello draft',
        ])->assertOk();
    }
}
