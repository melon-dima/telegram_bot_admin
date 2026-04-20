<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $email = $request->email;
        if (! $email) {
            $base = preg_replace('/[^a-z0-9._-]/i', '', strtolower((string) $request->username)) ?: 'user';
            $candidate = "{$base}@local.invalid";
            $counter = 1;

            while (User::where('email', $candidate)->exists()) {
                $candidate = "{$base}+{$counter}@local.invalid";
                $counter++;
            }

            $email = $candidate;
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $email,
            'password' => Hash::make($request->password),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ],
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => auth('api')->user(),
        ]);
    }

    public function check(Request $request)
    {
        try {
            $user = auth('api')->user();
            if ($user) {
                return response()->json(['authenticated' => true, 'user' => $user]);
            }
        } catch (\Exception $e) {
        }

        return response()->json(['authenticated' => false]);
    }

    public function logout()
    {
        auth('api')->logout();

        return response()->json(['success' => true]);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }
}
