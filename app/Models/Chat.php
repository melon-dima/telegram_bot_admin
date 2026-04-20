<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = [
        'telegram_chat_id', 'bot_id', 'type',
        'title', 'username', 'first_name', 'last_name',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
