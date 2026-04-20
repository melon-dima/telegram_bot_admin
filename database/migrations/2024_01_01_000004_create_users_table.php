<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Здесь users — это telegram-пользователи, а не админы
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->bigInteger('telegram_id')->primary(); // telegram user id
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
