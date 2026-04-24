<?php

namespace Tests\Feature\Api;

use App\Models\Bot;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TokenLeakProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function authHeadersFor(User $user): array
    {
        $token = JWTAuth::fromUser($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    /**
     * @runInSeparateProcess
     */
    public function test_file_endpoint_does_not_leak_bot_token_when_telegram_download_fails(): void
    {
        $user = User::factory()->create();
        $bot = Bot::create([
            'name' => 'Test Bot',
            'token' => 'bot_token_secret_123',
            'owner_id' => $user->id,
            'is_active' => true,
            'mode' => 'poll',
        ]);

        $mock = Mockery::mock('overload:App\Services\TelegramClient');
        $mock->shouldReceive('downloadFile')
            ->once()
            ->andThrow(new \Exception(
                'cURL error 6 for https://api.telegram.org/bot'.$bot->token.'/getFile'
            ));

        $response = $this
            ->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/bots/'.$bot->id.'/get-file-url/test-file-id');

        $response->assertStatus(502)
            ->assertJson([
                'error' => 'Failed to download file from Telegram.',
            ]);

        $this->assertStringNotContainsString($bot->token, (string) $response->getContent());
        $this->assertStringNotContainsString('api.telegram.org', (string) $response->getContent());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_pin_endpoint_does_not_leak_bot_token_when_telegram_pin_fails(): void
    {
        $user = User::factory()->create();
        $bot = Bot::create([
            'name' => 'Test Bot',
            'token' => 'bot_token_secret_456',
            'owner_id' => $user->id,
            'is_active' => true,
            'mode' => 'poll',
        ]);

        $mock = Mockery::mock('overload:App\Services\TelegramClient');
        $mock->shouldReceive('pinChatMessage')
            ->once()
            ->andThrow(new \Exception(
                'cURL error 7 for https://api.telegram.org/bot'.$bot->token.'/pinChatMessage'
            ));

        $response = $this
            ->withHeaders($this->authHeadersFor($user))
            ->postJson('/api/bots/'.$bot->id.'/chats/12345/pin', [
                'message_id' => 111,
                'disable_notification' => false,
            ]);

        $response->assertStatus(502)
            ->assertJson([
                'error' => 'Failed to pin message via Telegram.',
            ]);

        $this->assertStringNotContainsString($bot->token, (string) $response->getContent());
        $this->assertStringNotContainsString('api.telegram.org', (string) $response->getContent());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_user_avatar_endpoint_proxies_file_without_redirecting_to_telegram_url(): void
    {
        $user = User::factory()->create();
        $bot = Bot::create([
            'name' => 'Avatar Bot',
            'token' => 'bot_token_secret_avatar_user',
            'owner_id' => $user->id,
            'is_active' => true,
            'mode' => 'poll',
        ]);

        $mock = Mockery::mock('overload:App\Services\TelegramClient');
        $mock->shouldReceive('getUserProfilePhotos')
            ->once()
            ->andReturn([
                'total_count' => 1,
                'photos' => [
                    [
                        ['file_id' => 'small_photo_id'],
                        ['file_id' => 'big_photo_id'],
                    ],
                ],
            ]);
        $mock->shouldReceive('downloadFile')
            ->once()
            ->with('big_photo_id')
            ->andReturn([
                'data' => 'avatar-binary-user',
                'file_name' => 'avatar.jpg',
                'mime_type' => 'image/jpeg',
            ]);

        $response = $this
            ->withHeaders($this->authHeadersFor($user))
            ->get('/api/bots/'.$bot->id.'/users/777/avatar');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('avatar-binary-user', $response->getContent());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_chat_avatar_endpoint_proxies_file_without_redirecting_to_telegram_url(): void
    {
        $user = User::factory()->create();
        $bot = Bot::create([
            'name' => 'Chat Avatar Bot',
            'token' => 'bot_token_secret_avatar_chat',
            'owner_id' => $user->id,
            'is_active' => true,
            'mode' => 'poll',
        ]);

        Chat::create([
            'telegram_chat_id' => 888,
            'bot_id' => $bot->id,
            'type' => 'private',
            'title' => null,
            'username' => null,
            'first_name' => 'Alice',
            'last_name' => null,
        ]);

        $mock = Mockery::mock('overload:App\Services\TelegramClient');
        $mock->shouldReceive('getUserProfilePhotos')
            ->once()
            ->andReturn([
                'total_count' => 1,
                'photos' => [
                    [
                        ['file_id' => 'small_chat_photo_id'],
                        ['file_id' => 'big_chat_photo_id'],
                    ],
                ],
            ]);
        $mock->shouldReceive('downloadFile')
            ->once()
            ->with('big_chat_photo_id')
            ->andReturn([
                'data' => 'avatar-binary-chat',
                'file_name' => 'chat-avatar.jpg',
                'mime_type' => 'image/jpeg',
            ]);

        $response = $this
            ->withHeaders($this->authHeadersFor($user))
            ->get('/api/bots/'.$bot->id.'/chats/888/avatar');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('avatar-binary-chat', $response->getContent());
    }
}
