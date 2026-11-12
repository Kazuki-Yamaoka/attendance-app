<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\UserLoginController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
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

Route::get('/', function () {
    return view('welcome');
});

// 2. メール認証誘導画面
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// 3. メールのリンクをクリックしたときの処理
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/attendance'); // 4-d. 認証完了後の勤怠登録画面などへ
})->middleware(['auth', 'signed'])->name('verification.verify');

// ★認証メール再送機能のルート
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('message', '認証メールを再送しました！');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// --- 【一般ユーザー用】 ---
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [UserLoginController::class, 'create'])->name('login');
    Route::post('/login', [UserLoginController::class, 'store']);
});

Route::middleware('auth', 'verified')->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/detail/{id}', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('report.index');
});

// --- 【管理者用】 ---
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'store']);
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.index');
    Route::get('/admin/staff/{id}', [AdminStaffController::class, 'index'])->name('admin.staff.index');
    Route::get('/admin/attendance/staff/{id}', [AdminStaffController::class, 'show'])->name('admin.staff.show');
    Route::post('/export', [AdminStaffController::class, 'export'])->name('admin.staff.export');

    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('admin.attendance.show');
    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'update'])->name('admin.attendance.update');

    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendanceCorrectionController::class, 'show'])->name('application.show');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AttendanceCorrectionController::class, 'approve'])->name('application.approve');
    // ★ ここに管理者用ログアウトのルートを追加
    Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
});

// 申請一覧画面（一般・管理者共通、認証はコントローラーで設定済み）
Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'index'])->middleware('auth:web,admin')->name('application.index');
