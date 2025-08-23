<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;

// 未ログイン（webガード）向け: 管理者ログイン画面・処理
Route::middleware('guest')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login.show');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.perform');
});

// ログイン済み（webガード）＆ 管理者権限のみ
Route::middleware(['auth', 'can:admin'])->group(function () {
    // ダッシュボード（必要なら）
    Route::get('/dashboard', [AdminAttendanceController::class, 'dashboard'])->name('dashboard');

    // 勤怠一覧・詳細
    Route::get('/attendances', [AdminAttendanceController::class, 'index'])->name('attendances.index');
    Route::get('/attendances/{id}', [AdminAttendanceController::class, 'show'])->name('attendances.show');

    // スタッフ一覧・スタッフ別勤怠一覧
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/attendances', [AdminAttendanceController::class, 'userAttendances'])->name('users.attendances');

    // 申請一覧・申請詳細（承認画面）
    Route::get('/requests', [AdminRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{id}', [AdminRequestController::class, 'show'])->name('requests.show');

    // 承認 / 却下（処理系）
    Route::post('/requests/{id}/approve', [AdminRequestController::class, 'approve'])->name('requests.approve');
    Route::post('/requests/{id}/reject',  [AdminRequestController::class, 'reject'])->name('requests.reject');
});
