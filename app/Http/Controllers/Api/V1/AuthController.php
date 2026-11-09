<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 認証チェック
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。'
            ], 401);
        }

        $user = Auth::user();

        // Sanctumのトークン発行
        $token = $user->createToken('attendance-api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }
}
