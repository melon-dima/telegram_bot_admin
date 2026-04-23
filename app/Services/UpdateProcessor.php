<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Chat;
use App\Models\Message;
use App\Models\TelegramUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateProcessor
{
    public function __construct(protected Bot $bot) {}

    public function process(array $update): void
    {
        // Обрабатываем только message / edited_message / channel_post
        $tgMessage = $update['message']
            ?? $update['edited_message']
            ?? $update['channel_post']
            ?? $update['edited_channel_post']
            ?? null;

        if (! $tgMessage) {
            Log::debug('Update has no message', ['update_id' => $update['update_id'] ?? null]);

            return;
        }

        try {
            DB::transaction(function () use ($tgMessage) {
                $chat = $this->upsertChat($tgMessage['chat']);
                $user = isset($tgMessage['from']) ? $this->upsertUser($tgMessage['from']) : null;
                $this->saveMessage($chat, $user, $tgMessage);
            });
        } catch (\Exception $e) {
            Log::error('Failed to process update', [
                'error' => $e->getMessage(),
                'update' => $update,
            ]);
        }
    }

    protected function upsertChat(array $tgChat): Chat
    {
        return Chat::updateOrCreate(
            [
                'telegram_chat_id' => $tgChat['id'],
                'bot_id' => $this->bot->id,
            ],
            [
                'type' => $tgChat['type'] ?? 'private',
                'title' => $tgChat['title'] ?? null,
                'username' => $tgChat['username'] ?? null,
                'first_name' => $tgChat['first_name'] ?? null,
                'last_name' => $tgChat['last_name'] ?? null,
            ]
        );
    }

    protected function upsertUser(array $tgUser): TelegramUser
    {
        return TelegramUser::updateOrCreate(
            ['telegram_id' => $tgUser['id']],
            [
                'first_name' => $tgUser['first_name'] ?? 'Unknown',
                'last_name' => $tgUser['last_name'] ?? null,
                'username' => $tgUser['username'] ?? null,
            ]
        );
    }

    protected function saveMessage(Chat $chat, ?TelegramUser $user, array $tgMessage): Message
    {
        // Избегаем дубликатов входящих сообщений от polling/webhook
        $existing = Message::where('chat_id', $chat->id)
            ->where('bot_id', $this->bot->id)
            ->where('message_id', $tgMessage['message_id'])
            ->where('direction', 'in')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Текст или caption
        $text = $tgMessage['text']
            ?? $tgMessage['caption']
            ?? $this->extractServiceText($tgMessage);

        // reply_to_message_id (наш внутренний db id, если найдём)
        $replyToDbId = null;
        if (isset($tgMessage['reply_to_message']['message_id'])) {
            $replyOriginal = Message::where('chat_id', $chat->id)
                ->where('bot_id', $this->bot->id)
                ->where('message_id', $tgMessage['reply_to_message']['message_id'])
                ->first();
            if ($replyOriginal) {
                $replyToDbId = $replyOriginal->id;
            }
        }

        return Message::create([
            'chat_id' => $chat->id,
            'from_user_id' => $user?->telegram_id,
            'bot_id' => $this->bot->id,
            'sent_by_user_id' => null,
            'reply_to_message_id' => $replyToDbId,
            'message_id' => $tgMessage['message_id'],
            'direction' => 'in',
            'text' => $text,
            'raw_json' => $tgMessage,
            'date' => isset($tgMessage['date'])
                ? Carbon::createFromTimestamp($tgMessage['date'])
                : now(),
        ]);
    }

    /**
     * Формирует текст для системных сообщений (joined, left, pinned и т.д.)
     */
    protected function extractServiceText(array $tgMessage): ?string
    {
        if (! empty($tgMessage['new_chat_members'])) {
            $names = array_map(
                fn ($u) => trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: ('@'.($u['username'] ?? 'user')),
                $tgMessage['new_chat_members']
            );

            return count($names) === 1
                ? "👤 {$names[0]} присоединился к чату"
                : '👥 '.implode(', ', $names).' присоединились к чату';
        }

        if (! empty($tgMessage['left_chat_member'])) {
            $u = $tgMessage['left_chat_member'];
            $name = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: ('@'.($u['username'] ?? 'user'));

            return "👋 {$name} покинул чат";
        }

        if (isset($tgMessage['new_chat_title'])) {
            return '✏️ Название чата изменено на: '.$tgMessage['new_chat_title'];
        }

        if (isset($tgMessage['new_chat_photo'])) {
            return '🖼 Фото чата изменено';
        }

        if (isset($tgMessage['delete_chat_photo'])) {
            return '🗑 Фото чата удалено';
        }

        if (isset($tgMessage['pinned_message'])) {
            return '📌 Сообщение закреплено';
        }

        // Медиа без подписи
        if (isset($tgMessage['photo'])) {
            return null;
        }
        if (isset($tgMessage['video'])) {
            return null;
        }
        if (isset($tgMessage['document'])) {
            return null;
        }
        if (isset($tgMessage['voice'])) {
            return null;
        }
        if (isset($tgMessage['video_note'])) {
            return null;
        }
        if (isset($tgMessage['sticker'])) {
            return null;
        }
        if (isset($tgMessage['audio'])) {
            return null;
        }

        return null;
    }
}
