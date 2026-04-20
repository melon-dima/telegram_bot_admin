<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAccess;
use App\Models\Chat;
use App\Models\Message;
use App\Models\TelegramUser;
use App\Models\User;
use App\Services\TelegramClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    protected function sanitizeTelegramError(Bot $bot, \Throwable $e): string
    {
        return str_replace((string) $bot->token, '[redacted]', (string) $e->getMessage());
    }

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

    protected function formatMessage(Message $msg): array
    {
        $data = [
            'id' => (string) $msg->id,
            'message_id' => $msg->message_id ? (string) $msg->message_id : null,
            'direction' => $msg->direction,
            'text' => $msg->text,
            'date' => $msg->date->toIso8601String(),
            'rawJson' => $msg->raw_json,
        ];

        if ($msg->local_file_path) {
            $data['localFilePath'] = $msg->local_file_path;
        }

        if ($msg->sent_by_user_id) {
            $user = User::find($msg->sent_by_user_id);
            if ($user) {
                $data['sentByUser'] = [
                    'id' => (string) $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ];

                // backward compatibility for old clients
                $data['sentByAdmin'] = [
                    'id' => (string) $user->id,
                    'username' => $user->username,
                ];
            }
        }

        if ($msg->reply_to_message_id) {
            $reply = Message::find($msg->reply_to_message_id);
            if ($reply) {
                $replyData = [
                    'message_id' => (string) $reply->id,
                    'text' => $reply->text ?? '',
                ];
                if ($reply->from_user_id) {
                    $replyUser = TelegramUser::find($reply->from_user_id);
                    if ($replyUser) {
                        $replyData['from_user'] = [
                            'id' => (string) $replyUser->telegram_id,
                            'first_name' => $replyUser->first_name,
                            'username' => $replyUser->username,
                        ];
                    }
                }
                $data['replyTo'] = $replyData;
            }
        }

        if ($msg->from_user_id) {
            $user = TelegramUser::find($msg->from_user_id);
            if ($user) {
                $data['from_user'] = [
                    'telegramId' => (string) $user->telegram_id,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'username' => $user->username,
                ];
            }
        }

        return $data;
    }

    public function getChatMessages(Request $request, $botId, $chatId)
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

        $query = Message::where('chat_id', $chat->id)
            ->where('bot_id', $botId);

        $after = $request->query('after');
        if ($after && $after !== '0') {
            $query->where('id', '>', $after);
        }

        $messages = $query->orderBy('id', 'asc')->get();

        $result = $messages->map(fn ($m) => $this->formatMessage($m));

        return response()->json($result);
    }

    public function getChatAllMessages(Request $request, $botId, $chatId)
    {
        return $this->getChatMessages($request, $botId, $chatId);
    }

    public function sendChatMessage(Request $request, $botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'text' => 'required|string',
            'reply_to_message_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $chat = Chat::where('telegram_chat_id', $chatId)
            ->where('bot_id', $botId)->first();
        if (! $chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        // Находим telegram message_id для reply
        $replyToTelegramMessageId = null;
        if ($request->reply_to_message_id) {
            $replyMsg = Message::find($request->reply_to_message_id);
            if ($replyMsg && $replyMsg->raw_json) {
                $replyToTelegramMessageId = $replyMsg->raw_json['message_id'] ?? null;
            }
        }

        try {
            $tc = new TelegramClient($bot->token);
            $tgMsg = $tc->sendMessage(
                (int) $chat->telegram_chat_id,
                $request->text,
                $replyToTelegramMessageId
            );
        } catch (\Throwable $e) {
            $errMsg = (string) $e->getMessage();
            $safeErr = $this->sanitizeTelegramError($bot, $e);

            Log::warning('Telegram sendChatMessage failed', [
                'bot_id' => (string) $botId,
                'chat_id' => (string) $chatId,
                'error' => $safeErr,
            ]);

            if (str_contains($errMsg, "bots can't send messages to bots")) {
                return response()->json(['error' => 'Cannot send messages to bots.'], 403);
            }
            if (str_contains($errMsg, 'chat not found') || str_contains($errMsg, 'user not found')) {
                return response()->json(['error' => 'User not found or chat not accessible'], 404);
            }
            if (str_contains($errMsg, 'blocked')) {
                return response()->json(['error' => 'User has blocked the bot'], 403);
            }
            if (str_contains($errMsg, 'group chat was upgraded to a supergroup')) {
                $chat->type = 'supergroup';
                $chat->save();

                return response()->json(['error' => 'Group chat was upgraded to a supergroup.'], 400);
            }

            return response()->json(['error' => 'Failed to send message via Telegram.'], 502);
        }

        $message = Message::create([
            'chat_id' => $chat->id,
            'from_user_id' => null,
            'bot_id' => $botId,
            'sent_by_user_id' => $userId,
            'reply_to_message_id' => $request->reply_to_message_id,
            'message_id' => $tgMsg['message_id'] ?? null,
            'direction' => 'out',
            'text' => $request->text,
            'raw_json' => $tgMsg,
            'date' => now(),
        ]);

        return response()->json($this->formatMessage($message));
    }

    public function sendChatPhoto(Request $request, $botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (! $request->hasFile('photo')) {
            return response()->json(['error' => 'Photo file is required'], 400);
        }

        $chat = Chat::where('telegram_chat_id', $chatId)
            ->where('bot_id', $botId)->first();
        if (! $chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        $file = $request->file('photo');
        $tmpPath = $file->getRealPath();
        $caption = $request->input('caption');

        try {
            $tc = new TelegramClient($bot->token);
            $tgMsg = $tc->sendPhoto((int) $chat->telegram_chat_id, $tmpPath, $caption);
        } catch (\Throwable $e) {
            $errMsg = (string) $e->getMessage();
            $safeErr = $this->sanitizeTelegramError($bot, $e);

            Log::warning('Telegram sendChatPhoto failed', [
                'bot_id' => (string) $botId,
                'chat_id' => (string) $chatId,
                'error' => $safeErr,
            ]);

            if (str_contains($errMsg, 'group chat was upgraded to a supergroup')) {
                $chat->type = 'supergroup';
                $chat->save();

                return response()->json(['error' => 'Group upgraded to supergroup.'], 400);
            }

            return response()->json(['error' => 'Failed to send photo via Telegram.'], 502);
        }

        $text = $caption ?: null;

        $message = Message::create([
            'chat_id' => $chat->id,
            'from_user_id' => null,
            'bot_id' => $botId,
            'sent_by_user_id' => $userId,
            'message_id' => $tgMsg['message_id'] ?? null,
            'direction' => 'out',
            'text' => $text,
            'raw_json' => $tgMsg,
            'date' => now(),
        ]);

        return response()->json($this->formatMessage($message));
    }

    public function pinChatMessage(Request $request, $botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'message_id' => 'required|integer',
            'disable_notification' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $tc->pinChatMessage(
                (int) $chatId,
                (int) $request->message_id,
                (bool) $request->input('disable_notification', false)
            );
        } catch (\Throwable $e) {
            $errMsg = (string) $e->getMessage();
            $safeErr = $this->sanitizeTelegramError($bot, $e);

            Log::warning('Telegram pinChatMessage failed', [
                'bot_id' => (string) $botId,
                'chat_id' => (string) $chatId,
                'message_id' => (string) $request->message_id,
                'error' => $safeErr,
            ]);

            if (str_contains($errMsg, "service messages can't be pinned")) {
                return response()->json([
                    'error' => 'Service messages cannot be pinned. Only regular user messages can be pinned.',
                ], 400);
            }

            return response()->json(['error' => 'Failed to pin message via Telegram.'], 502);
        }

        return response()->json(['success' => true]);
    }

    public function unpinChatMessage(Request $request, $botId, $chatId)
    {
        $userId = auth('api')->id();
        $bot = $this->checkBotAccess($botId, $userId);
        if (! $bot) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $tc = new TelegramClient($bot->token);
            $messageId = $request->input('message_id');
            if ($messageId) {
                $tc->unpinChatMessage((int) $chatId, (int) $messageId);
            } else {
                $tc->unpinAllChatMessages((int) $chatId);
            }
        } catch (\Throwable $e) {
            $safeErr = $this->sanitizeTelegramError($bot, $e);

            Log::warning('Telegram unpinChatMessage failed', [
                'bot_id' => (string) $botId,
                'chat_id' => (string) $chatId,
                'message_id' => $request->input('message_id'),
                'error' => $safeErr,
            ]);

            return response()->json(['error' => 'Failed to unpin message via Telegram.'], 502);
        }

        return response()->json(['success' => true]);
    }
}
