<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotState extends Model
{
    protected $primaryKey = 'bot_id';

    public $incrementing = false;

    protected $keyType = 'integer';

    protected $fillable = ['bot_id', 'last_update_id'];
}
