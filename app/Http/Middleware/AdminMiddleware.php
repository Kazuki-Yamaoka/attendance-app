<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. ログインしており、かつ is_admin が true（1）であるか？
        if (Auth::guard('admin')->check() && Auth::guard('admin')->user()->is_admin) 
        {
            return $next($request); // 条件クリアならそのまま通す
        }

        // 2. 未ログイン、または一般ユーザーの場合は管理者ログイン画面へ強制送還
        return redirect()->route('admin.login')->with('error', '管理者権限がありません。');
    }
}
