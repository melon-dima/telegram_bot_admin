<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('telegram_chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('type', 20); // private, group, supergroup, channel
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->unique(['telegram_chat_id', 'bot_id']);
            $table->index('bot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
