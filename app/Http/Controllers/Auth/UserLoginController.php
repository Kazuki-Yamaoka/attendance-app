<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class UserLoginController extends Controller
{
    public function create()
    {
        return view('user.user-login'); // 一般用ログイン画面
    }

    public function store(UserLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) 
        {
            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません。'],
            ]);
        }   

        $request->session()->regenerate(); 

        return redirect()->intended('/attendance'); // ログイン成功時
    }
}
