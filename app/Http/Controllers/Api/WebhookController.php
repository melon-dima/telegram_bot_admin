<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Services\TelegramClient;
use App\Services\UpdateProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected function sanitizeTelegramError(Bot $bot, \Throwable $e): string
    {
        return str_replace((string) $bot->token, '[redacted]', (string) $e->getMessage());
    }

    /**
     * Приём обновлений от Telegram.
     * URL должен содержать "секрет" (token или отдельный secret), иначе кто угодно сможет писать.
     */
    public function handle(Request $request, $botId, $secret)
    {
        $bot = Bot::where('id', $botId)
            ->where('is_active', true)
            ->first();

        if (! $bot) {
            Log::warning('Webhook: bot not found or inactive', ['bot_id' => $botId]);

            return response()->json(['ok' => false], 404);
        }

        // Простейшая проверка секрета — сравниваем с хэшем токена
        $expected = hash('sha256', $bot->token);
        if (! hash_equals($expected, $secret)) {
            Log::warning('Webhook: invalid secret', ['bot_id' => $botId]);

            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();

        try {
            (new UpdateProcessor($bot))->process($update);
        } catch (\Throwable $e) {
            Log::error('Webhook processing error', [
                'bot_id' => $botId,
                'error' => $this->sanitizeTelegramError($bot, $e),
            ]);
            // Всё равно отвечаем 200, чтобы Telegram не ретраил бесконечно
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Регистрация webhook на стороне Telegram.
     * Вызывать вручную после создания бота или при смене URL.
     */
    public function setWebhook(Request $request, $botId)
    {
        $userId = auth('api')->id();

        $bot = Bot::find($botId);
        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }
        if ($bot->owner_id != $userId) {
            return response()->json(['error' => 'Only owner can set webhook'], 403);
        }

        $baseUrl = rtrim($bot->webhook_url ?: config('app.url'), '/');
        $secret = hash('sha256', $bot->token);
        $url = "{$baseUrl}/api/telegram/webhook/{$bot->id}/{$secret}";

        try {
            $tc = new TelegramClient($bot->token);
            $tc->setWebhook($url);
        } catch (\Throwable $e) {
            Log::warning('Telegram setWebhook failed', [
                'bot_id' => (string) $botId,
                'error' => $this->sanitizeTelegramError($bot, $e),
            ]);

            return response()->json(['error' => 'Failed to set webhook in Telegram.'], 502);
        }

        return response()->json(['success' => true, 'url' => $url]);
    }

    public function deleteWebhook(Request $request, $botId)
    {
        $userId = auth('api')->id();

        $bot = Bot::find($botId);
        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }
        if ($bot->owner_id != $userId) {
            return response()->json(['error' => 'Only owner can delete webhook'], 403);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $tc->deleteWebhook();
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteWebhook failed', [
                'bot_id' => (string) $botId,
                'error' => $this->sanitizeTelegramError($bot, $e),
            ]);

            return response()->json(['error' => 'Failed to delete webhook in Telegram.'], 502);
        }

        return response()->json(['success' => true]);
    }
}
