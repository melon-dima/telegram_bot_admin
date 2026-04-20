<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    protected $fillable = [
        'token', 'name', 'owner_id', 'is_active', 'mode', 'webhook_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = ['token'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function accesses()
    {
        return $this->hasMany(BotAccess::class);
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
