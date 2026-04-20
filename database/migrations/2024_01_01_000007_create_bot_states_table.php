<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_states', function (Blueprint $table) {
            $table->unsignedBigInteger('bot_id')->primary();
            $table->bigInteger('last_update_id')->default(0);
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_states');
    }
};
