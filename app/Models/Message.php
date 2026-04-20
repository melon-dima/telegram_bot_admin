<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'chat_id', 'from_user_id', 'bot_id', 'sent_by_user_id',
        'reply_to_message_id', 'message_id', 'direction', 'text',
        'raw_json', 'local_file_path', 'date',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'date' => 'datetime',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function sentByUser()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(TelegramUser::class, 'from_user_id', 'telegram_id');
    }
}
