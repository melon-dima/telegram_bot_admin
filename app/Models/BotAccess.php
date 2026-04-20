<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotAccess extends Model
{
    protected $table = 'bot_access';

    protected $fillable = ['bot_id', 'user_id'];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
