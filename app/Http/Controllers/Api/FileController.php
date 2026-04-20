<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAccess;
use App\Services\TelegramClient;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    protected function checkBotAccess($botId, $userId): ?Bot
    {
        $bot = Bot::find($botId);
        if (! $bot) {
            return null;
        }
        if ($bot->owner_id == $userId) {
            return $bot;
        }
        $hasAccess = BotAccess::where('bot_id', $botId)
            ->where('user_id', $userId)->exists();

        return $hasAccess ? $bot : null;
    }

    public function getFileUrl($botId, $fileId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (empty($fileId)) {
            return response()->json(['error' => 'File ID is required'], 400);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $fileContent = $tc->downloadFile($fileId);
        } catch (\Throwable $e) {
            $safeMessage = str_replace((string) $bot->token, '[redacted]', (string) $e->getMessage());

            Log::warning('Telegram file download failed', [
                'bot_id' => (string) $botId,
                'file_id' => (string) $fileId,
                'error' => $safeMessage,
            ]);

            return response()->json([
                'error' => 'Failed to download file from Telegram.',
            ], 502);
        }

        return response($fileContent['data'], 200)
            ->header('Content-Type', $fileContent['mime_type'])
            ->header('Content-Disposition', 'inline; filename="'.$fileContent['file_name'].'"')
            ->header('Content-Length', strlen($fileContent['data']))
            ->header('X-File-Name', $fileContent['file_name']);
    }
}
