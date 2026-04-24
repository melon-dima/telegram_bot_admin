<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotController extends Controller
{
    protected function isServiceRequest(Request $request): bool
    {
        $expected = config('services.service_auth.token');
        $provided = $request->header('X-Service-Token');

        return is_string($expected)
            && $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    public function index(Request $request)
    {
        if ($this->isServiceRequest($request)) {
            $bots = Bot::where('is_active', true)
                ->where('mode', 'poll')
                ->get();

            return response()->json(
                $bots->map(function ($bot) {
                    return [
                        'id' => (string) $bot->id,
                        'name' => $bot->name,
                        'token' => $bot->token,
                        'is_active' => (bool) $bot->is_active,
                        'mode' => $bot->mode,
                    ];
                })->values()
            );
        }

        try {
            $userId = auth('api')->id();
        } catch (\Throwable $e) {
            $userId = null;
        }

        if (! $userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $bots = Bot::where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->where('owner_id', $userId)
                    ->orWhereHas('accesses', fn ($q2) => $q2->where('user_id', $userId));
            })
            ->get();

        $result = $bots->map(function ($bot) use ($userId) {
            return [
                'id' => (string) $bot->id,
                'name' => $bot->name,
                'isActive' => $bot->is_active,
                'mode' => $bot->mode,
                'isOwner' => $bot->owner_id == $userId,
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'token' => 'required|string',
            'mode' => 'nullable|in:poll,webhook',
            'webhookUrl' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $bot = Bot::create([
            'name' => $request->name,
            'token' => $request->token,
            'owner_id' => auth('api')->id(),
            'is_active' => true,
            'mode' => $request->mode ?? 'poll',
            'webhook_url' => $request->webhookUrl,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $bot->id,
                'name' => $bot->name,
                'isActive' => $bot->is_active,
                'mode' => $bot->mode,
                'isOwner' => true,
            ],
        ]);
    }

    public function show($botId)
    {
        $userId = auth('api')->id();
        $bot = Bot::find($botId);

        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        $isOwner = $bot->owner_id == $userId;
        $hasAccess = $isOwner || BotAccess::where('bot_id', $botId)->where('user_id', $userId)->exists();

        if (! $hasAccess) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $bot->id,
                'name' => $bot->name,
                'isActive' => $bot->is_active,
                'mode' => $bot->mode,
                'webhookUrl' => $bot->webhook_url,
                'isOwner' => $isOwner,
            ],
        ]);
    }

    public function update(Request $request, $botId)
    {
        $userId = auth('api')->id();
        $bot = Bot::find($botId);

        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        if ($bot->owner_id != $userId) {
            return response()->json(['error' => 'Only owner can update bot'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string',
            'isActive' => 'nullable|boolean',
            'mode' => 'nullable|in:poll,webhook',
            'webhookUrl' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if ($request->has('name')) {
            $bot->name = $request->name;
        }
        if ($request->has('isActive')) {
            $bot->is_active = $request->isActive;
        }
        if ($request->has('mode')) {
            $bot->mode = $request->mode;
        }
        if ($request->has('webhookUrl')) {
            $bot->webhook_url = $request->webhookUrl;
        }

        $bot->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $bot->id,
                'name' => $bot->name,
                'isActive' => $bot->is_active,
                'mode' => $bot->mode,
                'webhookUrl' => $bot->webhook_url,
            ],
        ]);
    }

    public function destroy($botId)
    {
        $userId = auth('api')->id();
        $bot = Bot::find($botId);

        if (! $bot) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        if ($bot->owner_id != $userId) {
            return response()->json(['error' => 'Only owner can delete bot'], 403);
        }

        $bot->delete();

        return response()->json(['success' => true]);
    }
}
