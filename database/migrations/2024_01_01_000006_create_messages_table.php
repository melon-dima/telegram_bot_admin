<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('chat_id');
            $table->bigInteger('from_user_id')->nullable(); // telegram user id
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->unsignedBigInteger('sent_by_user_id')->nullable();
            $table->unsignedBigInteger('reply_to_message_id')->nullable(); // db id
            $table->bigInteger('message_id')->nullable(); // telegram message id
            $table->string('direction', 10); // in | out
            $table->text('text')->nullable();
            $table->json('raw_json')->nullable();
            $table->string('local_file_path')->nullable();
            $table->timestamp('date');
            $table->timestamps();

            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('set null');
            $table->foreign('sent_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('set null');

            $table->index(['chat_id', 'bot_id']);
            $table->index(['from_user_id', 'bot_id']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
