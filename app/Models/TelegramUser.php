<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    protected $table = 'telegram_users';

    protected $primaryKey = 'telegram_id';

    public $incrementing = false;

    protected $keyType = 'integer';

    protected $fillable = [
        'telegram_id', 'first_name', 'last_name', 'username',
    ];
}
