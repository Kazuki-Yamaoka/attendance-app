<?php

use App\Http\Controllers\Auth\UserLoginController;
use App\Http\Controllers\Auth\AdminLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


// --- 【一般ユーザー用】 ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [UserLoginController::class, 'create'])->name('login');
    Route::post('/login', [UserLoginController::class, 'store']);
});

// 仮ルート（Chapter 6で本実装に置き換え）
Route::middleware('auth')->group(function () {
    Route::get('/attendance', fn() => '勤怠登録（準備中）')->name('attendance.index');
    Route::get('/stamp_correction_request/list', fn() => '申請一覧（準備中）')->name('request.index');
    Route::get('/attendance/report', fn() => '勤怠統計レポート（準備中）')->name('report.index');
});


// --- 【管理者用】 ---
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'store']);
});

// （参考）管理者専用エリアのミドルウェア保護などはここにぶら下げる

Route::middleware('admin')->group(function () {
    Route::get('/admin/attendance/list', fn() => '管理者用勤怠一覧（準備中）')->name('admin.attendance.index');
    Route::get('/admin/staff/list', fn() => '管理者用スタッフ一覧（準備中）')->name('admin.staff.index');
    Route::get('/stamp_correction_request/list', fn() => '管理者用申請一覧（準備中）')->name('admin.request.index');
});
