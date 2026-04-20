<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAccess;
use App\Models\Chat;
use App\Models\Message;
use App\Models\TelegramUser;
use App\Services\TelegramClient;

class UserController extends Controller
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

    public function getBotUsers($botId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Пользователи через сообщения
        $users = TelegramUser::select('telegram_users.*')
            ->selectRaw('(SELECT COUNT(*) FROM messages WHERE messages.from_user_id = telegram_users.telegram_id AND messages.bot_id = ?) as message_count', [$botId])
            ->whereExists(function ($q) use ($botId) {
                $q->select('id')->from('messages')
                    ->whereColumn('messages.from_user_id', 'telegram_users.telegram_id')
                    ->where('messages.bot_id', $botId);
            })
            ->get();

        $chats = Chat::where('bot_id', $botId)->get();

        $result = [];

        foreach ($users as $u) {
            $result[] = [
                'telegramId' => (string) $u->telegram_id,
                'firstName' => $u->first_name,
                'lastName' => $u->last_name,
                'username' => $u->username,
                '_count' => ['messages' => (int) $u->message_count],
                'type' => 'user',
            ];
        }

        foreach ($chats as $chat) {
            if ($chat->type === 'private') {
                continue;
            }
            if ($chat->telegram_chat_id > 0) {
                continue;
            }

            $msgCount = Message::where('chat_id', $chat->id)
                ->where('bot_id', $botId)->count();

            $info = [
                'telegramId' => (string) $chat->telegram_chat_id,
                'type' => 'chat',
                'chatType' => $chat->type,
                '_count' => ['messages' => $msgCount],
            ];

            if ($chat->title) {
                $info['firstName'] = $chat->title;
                $info['title'] = $chat->title;
            }
            if ($chat->username) {
                $info['username'] = $chat->username;
            }
            if ($chat->first_name) {
                $info['firstName'] = $chat->first_name;
            }
            if ($chat->last_name) {
                $info['lastName'] = $chat->last_name;
            }

            $result[] = $info;
        }

        return response()->json($result);
    }

    public function getUser($botId, $telegramId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $telegramIdInt = (int) $telegramId;

        if ($telegramIdInt < 0) {
            $chat = Chat::where('telegram_chat_id', $telegramIdInt)
                ->where('bot_id', $botId)->first();
            if (! $chat) {
                return response()->json(['error' => 'Chat not found'], 404);
            }

            $msgCount = Message::where('chat_id', $chat->id)
                ->where('bot_id', $botId)->count();

            $result = [
                'telegramId' => (string) $chat->telegram_chat_id,
                'type' => 'chat',
                'chatType' => $chat->type,
                '_count' => ['messages' => $msgCount],
            ];

            if ($chat->title) {
                $result['title'] = $chat->title;
                $result['firstName'] = $chat->title;
            }
            if ($chat->username) {
                $result['username'] = $chat->username;
            }
            if ($chat->first_name) {
                $result['firstName'] = $chat->first_name;
            }
            if ($chat->last_name) {
                $result['lastName'] = $chat->last_name;
            }

            return response()->json($result);
        }

        $user = TelegramUser::find($telegramIdInt);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $count = Message::where('from_user_id', $user->telegram_id)
            ->where('bot_id', $botId)->count();

        return response()->json([
            'telegramId' => (string) $user->telegram_id,
            'username' => $user->username,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'type' => 'user',
            '_count' => ['messages' => $count],
        ]);
    }

    public function getUserAvatar($botId, $telegramId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if ((int) $telegramId < 0) {
            abort(404);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $photos = $tc->getUserProfilePhotos((int) $telegramId, 1);

            if (($photos['total_count'] ?? 0) == 0 || empty($photos['photos'][0])) {
                abort(404);
            }

            $photo = end($photos['photos'][0]);
            $fileContent = $tc->downloadFile($photo['file_id']);

            return response($fileContent['data'], 200)
                ->header('Content-Type', $fileContent['mime_type'])
                ->header('Content-Disposition', 'inline; filename="'.$fileContent['file_name'].'"')
                ->header('Content-Length', strlen($fileContent['data']))
                ->header('X-File-Name', $fileContent['file_name']);
        } catch (\Exception $e) {
            abort(404);
        }
    }
}
