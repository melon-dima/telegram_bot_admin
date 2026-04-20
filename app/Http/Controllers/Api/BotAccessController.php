<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotAccessController extends Controller
{
    public function index($botId)
    {
        $userId = auth('api')->id();
        $bot = Bot::find($botId);

        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        if ($bot->owner_id != $userId) {
            return response()->json(['error' => 'Only owner can view access'], 403);
        }

        $accesses = BotAccess::with('user')->where('bot_id', $botId)->get();

        $result = $accesses->map(function ($a) {
            return [
                'userId' => (string) $a->user_id,
                'username' => $a->user->username ?? null,
                'email' => $a->user->email ?? null,
            ];
        });

        return response()->json($result);
    }

    public function grant(Request $request, $botId)
    {
        $userId = auth('api')->id();
        $bot = Bot::find($botId);

        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        if ($bot->owner_id != $userId) {
            return response()->json(['error' => 'Only owner can grant access'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $user = User::where('username', $request->username)->first();

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($user->id == $bot->owner_id) {
            return response()->json(['error' => 'Owner already has access'], 400);
        }

        if (BotAccess::where('bot_id', $botId)->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'Access already granted'], 409);
        }

        BotAccess::create([
            'bot_id' => $botId,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'userId' => (string) $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ],
        ]);
    }

    public function revoke($botId, $userId)
    {
        $currentUserId = auth('api')->id();
        $bot = Bot::find($botId);

        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        if ($bot->owner_id != $currentUserId) {
            return response()->json(['error' => 'Only owner can revoke access'], 403);
        }

        BotAccess::where('bot_id', $botId)->where('user_id', $userId)->delete();

        return response()->json(['success' => true]);
    }
}
