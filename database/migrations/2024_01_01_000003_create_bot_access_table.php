<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_access', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('user_id'); // app user id
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['bot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_access');
    }
};
