<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAccess;
use App\Models\Chat;
use App\Services\TelegramClient;

class ChatController extends Controller
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

    public function getBotChats($botId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $chats = Chat::where('bot_id', $botId)->get();

        $result = $chats->map(function ($chat) {
            $info = [
                'telegram_chat_id' => (string) $chat->telegram_chat_id,
                'type' => $chat->type,
                'created_at' => $chat->created_at,
            ];
            if ($chat->title) {
                $info['title'] = $chat->title;
            }
            if ($chat->username) {
                $info['username'] = $chat->username;
            }
            if ($chat->first_name) {
                $info['first_name'] = $chat->first_name;
            }
            if ($chat->last_name) {
                $info['last_name'] = $chat->last_name;
            }

            return $info;
        });

        return response()->json($result);
    }

    public function getChat($botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $chat = Chat::where('telegram_chat_id', $chatId)
            ->where('bot_id', $botId)->first();

        if (! $chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        $info = [
            'telegramId' => (string) $chat->telegram_chat_id,
            'type' => $chat->type,
            'created_at' => $chat->created_at,
        ];

        if ($chat->title) {
            $info['title'] = $chat->title;
            $info['firstName'] = $chat->title;
        }
        if ($chat->username) {
            $info['username'] = $chat->username;
        }
        if ($chat->first_name) {
            $info['firstName'] = $chat->first_name;
            $info['first_name'] = $chat->first_name;
        }
        if ($chat->last_name) {
            $info['lastName'] = $chat->last_name;
            $info['last_name'] = $chat->last_name;
        }

        // Закрепленное сообщение
        try {
            $tc = new TelegramClient($bot->token);
            $tgChat = $tc->getChat((int) $chatId);
            if (isset($tgChat['pinned_message'])) {
                $pinned = $tgChat['pinned_message'];
                $pinnedMsg = [
                    'message_id' => $pinned['message_id'],
                    'text' => $pinned['text'] ?? null,
                    'date' => $pinned['date'] ?? null,
                ];
                if (isset($pinned['from'])) {
                    $pinnedMsg['from'] = [
                        'id' => $pinned['from']['id'],
                        'first_name' => $pinned['from']['first_name'] ?? null,
                        'last_name' => $pinned['from']['last_name'] ?? null,
                        'username' => $pinned['from']['username'] ?? null,
                    ];
                }
                $info['pinnedMessage'] = $pinnedMsg;
            }
        } catch (\Exception $e) {
        }

        return response()->json($info);
    }

    public function getChatAvatar($botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $chat = Chat::where('telegram_chat_id', $chatId)
            ->where('bot_id', $botId)->first();

        if (! $chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        if ($chat->type === 'private' && $chat->telegram_chat_id > 0) {
            try {
                $tc = new TelegramClient($bot->token);
                $photos = $tc->getUserProfilePhotos((int) $chat->telegram_chat_id, 1);
                if (($photos['total_count'] ?? 0) > 0 && ! empty($photos['photos'][0])) {
                    $photo = end($photos['photos'][0]);
                    $fileContent = $tc->downloadFile($photo['file_id']);

                    return response($fileContent['data'], 200)
                        ->header('Content-Type', $fileContent['mime_type'])
                        ->header('Content-Disposition', 'inline; filename="'.$fileContent['file_name'].'"')
                        ->header('Content-Length', strlen($fileContent['data']))
                        ->header('X-File-Name', $fileContent['file_name']);
                }
            } catch (\Exception $e) {
            }
            abort(404);
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" fill="#d8d8d8"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="18" fill="#999">👥</text></svg>';

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    public function getChatMembersCount($botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $count = $tc->getChatMembersCount((int) $chatId);
        } catch (\Exception $e) {
            $count = 0;
        }

        return response()->json(['count' => $count]);
    }

    public function getChatMembers($botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $members = $tc->getChatAdministrators((int) $chatId);
        } catch (\Exception $e) {
            $members = [];
        }

        $result = array_map(function ($m) {
            $info = [
                'id' => $m['user']['id'],
                'first_name' => $m['user']['first_name'] ?? null,
                'last_name' => $m['user']['last_name'] ?? null,
                'username' => $m['user']['username'] ?? null,
                'is_bot' => $m['user']['is_bot'] ?? false,
                'status' => $m['status'] ?? null,
            ];
            if (! empty($m['custom_title'])) {
                $info['custom_title'] = $m['custom_title'];
            }

            return $info;
        }, $members);

        return response()->json($result);
    }
}
