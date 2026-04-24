<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotAccessController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Incoming updates endpoint used by webhook mode and node-bot poller forwarding
Route::post('/telegram/webhook/{botId}/{secret}', [WebhookController::class, 'handle']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/check', [AuthController::class, 'check']);

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Bots
Route::prefix('bots')->group(function () {
    // Access model:
    // - X-Service-Token => service access for node-bot
    // - JWT             => user access
    Route::get('/', [BotController::class, 'index']);

    // All other bot operations require JWT auth
    Route::middleware('auth:api')->group(function () {
        Route::post('/', [BotController::class, 'store']);
        Route::get('/{botId}', [BotController::class, 'show']);
        Route::put('/{botId}', [BotController::class, 'update']);
        Route::delete('/{botId}', [BotController::class, 'destroy']);

        // Access
        Route::get('/{botId}/access', [BotAccessController::class, 'index']);
        Route::post('/{botId}/access', [BotAccessController::class, 'grant']);
        Route::delete('/{botId}/access/{userId}', [BotAccessController::class, 'revoke']);

        // Users
        Route::get('/{botId}/users', [UserController::class, 'getBotUsers']);
        Route::get('/{botId}/users/{telegramId}', [UserController::class, 'getUser']);
        Route::get('/{botId}/users/{telegramId}/avatar', [UserController::class, 'getUserAvatar']);

        // Chats
        Route::get('/{botId}/chats', [ChatController::class, 'getBotChats']);
        Route::get('/{botId}/chats/{chatId}', [ChatController::class, 'getChat']);
        Route::get('/{botId}/chats/{chatId}/avatar', [ChatController::class, 'getChatAvatar']);
        Route::get('/{botId}/chats/{chatId}/members/count', [ChatController::class, 'getChatMembersCount']);
        Route::get('/{botId}/chats/{chatId}/members', [ChatController::class, 'getChatMembers']);

        // Messages
        Route::get('/{botId}/chats/{chatId}/messages', [MessageController::class, 'getChatMessages']);
        Route::get('/{botId}/chats/{chatId}/messages/all', [MessageController::class, 'getChatAllMessages']);
        Route::post('/{botId}/chats/{chatId}/send', [MessageController::class, 'sendChatMessage']);
        Route::post('/{botId}/chats/{chatId}/send-photo', [MessageController::class, 'sendChatPhoto']);
        Route::post('/{botId}/chats/{chatId}/pin', [MessageController::class, 'pinChatMessage']);
        Route::post('/{botId}/chats/{chatId}/unpin', [MessageController::class, 'unpinChatMessage']);

        // Files
        Route::get('/{botId}/get-file-url/{fileId}', [FileController::class, 'getFileUrl']);
    });
});
