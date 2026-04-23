<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class TelegramClient
{
    protected string $token;

    protected string $baseUrl;

    public function __construct(string $token, string $baseUrl = 'https://api.telegram.org/bot')
    {
        $this->token = $token;
        $this->baseUrl = $baseUrl;
    }

    protected function url(string $method): string
    {
        return $this->baseUrl.$this->token.'/'.$method;
    }

    public function sendMessage(int $chatId, string $text, ?int $replyTo = null): array
    {
        $params = ['chat_id' => $chatId, 'text' => $text];
        if ($replyTo) {
            $params['reply_to_message_id'] = $replyTo;
        }

        $res = Http::post($this->url('sendMessage'), $params);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return $data['result'];
    }

    public function sendPhoto(int $chatId, string $filePath, ?string $caption = null): array
    {
        $multipart = [
            ['name' => 'chat_id', 'contents' => (string) $chatId],
            ['name' => 'photo', 'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)],
        ];
        if ($caption) {
            $multipart[] = ['name' => 'caption', 'contents' => $caption];
        }

        $client = new Client;
        $response = $client->post($this->url('sendPhoto'), ['multipart' => $multipart]);
        $data = json_decode($response->getBody()->getContents(), true);

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return $data['result'];
    }

    public function getFile(string $fileId): array
    {
        $res = Http::get($this->url('getFile'), ['file_id' => $fileId]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return $data['result'];
    }

    public function downloadFile(string $fileId): array
    {
        $file = $this->getFile($fileId);
        $filePath = $file['file_path'];
        $url = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";

        $content = @file_get_contents($url);
        if ($content === false) {
            throw new \Exception('Telegram file download failed');
        }

        $fileName = basename($filePath);
        $mimeType = 'application/octet-stream';
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($content);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        return [
            'data' => $content,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
        ];
    }

    public function getFileUrl(string $fileId): string
    {
        $file = $this->getFile($fileId);

        return "https://api.telegram.org/file/bot{$this->token}/{$file['file_path']}";
    }

    public function getUserProfilePhotos(int $userId, int $limit = 1): array
    {
        $res = Http::get($this->url('getUserProfilePhotos'), [
            'user_id' => $userId,
            'limit' => $limit,
        ]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return $data['result'];
    }

    public function getChat(int $chatId): array
    {
        $res = Http::get($this->url('getChat'), ['chat_id' => $chatId]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return $data['result'];
    }

    public function getChatMembersCount(int $chatId): int
    {
        $res = Http::get($this->url('getChatMemberCount'), ['chat_id' => $chatId]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return (int) $data['result'];
    }

    public function getChatAdministrators(int $chatId): array
    {
        $res = Http::get($this->url('getChatAdministrators'), ['chat_id' => $chatId]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }

        return $data['result'];
    }

    public function pinChatMessage(int $chatId, int $messageId, bool $disableNotification = false): void
    {
        $res = Http::post($this->url('pinChatMessage'), [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }
    }

    public function unpinChatMessage(int $chatId, ?int $messageId = null): void
    {
        $params = ['chat_id' => $chatId];
        if ($messageId) {
            $params['message_id'] = $messageId;
        }
        $res = Http::post($this->url('unpinChatMessage'), $params);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }
    }

    public function unpinAllChatMessages(int $chatId): void
    {
        $res = Http::post($this->url('unpinAllChatMessages'), ['chat_id' => $chatId]);
        $data = $res->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception($data['description'] ?? 'Telegram API error');
        }
    }
}
