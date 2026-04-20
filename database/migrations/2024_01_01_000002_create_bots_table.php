<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token');
            $table->string('name');
            $table->unsignedBigInteger('owner_id');
            $table->boolean('is_active')->default(true);
            $table->string('mode', 20)->default('poll'); // poll | webhook
            $table->string('webhook_url')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('owner_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
