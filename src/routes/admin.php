<?php

use Illuminate\Support\Facades\Route;

// 管理者コントローラ
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AttendanceEditRequestController;

// 未ログイン（adminガード）
Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login.form');
    Route::post('/login', [AdminLoginController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login');
});

// ログイン済み（adminガード）
Route::middleware('auth:admin')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

    // ダッシュボード（任意）
    Route::get('/dashboard', [AdminAttendanceController::class, 'daily'])->name('dashboard');

    // 日次一覧（従来URI）＆ PG08 エイリアス —— どちらも daily に集約
    Route::get('/attendances', [AdminAttendanceController::class, 'daily'])->name('attendances.daily');
    Route::get('/attendance/list', [AdminAttendanceController::class, 'daily'])->name('attendance.list.pg08');

    // 勤怠詳細＆更新（テストで利用：admin.attendances.show / update）
    Route::get('/attendances/{id}', [AdminAttendanceController::class, 'show'])
        ->whereNumber('id')->name('attendances.show');
    Route::put('/attendances/{id}', [AdminAttendanceController::class, 'update'])
        ->whereNumber('id')->name('attendances.update');

    // スタッフ一覧＆月次（テストで利用：admin.staff.index / admin.staff.attendances）
    Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/{user}/attendances', [AdminStaffController::class, 'monthly'])
        ->whereNumber('user')->name('staff.attendances');
    Route::get('/staff/{user}/attendances/csv', [AdminStaffController::class, 'exportCsv'])
        ->whereNumber('user')->name('staff.attendances.csv');

    // 申請一覧／詳細／承認（テストで利用：admin.requests.index / show / approve）
    Route::get('/requests', [AttendanceEditRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{id}', [AttendanceEditRequestController::class, 'show'])
        ->whereNumber('id')->name('requests.show');
    Route::post('/requests/{id}/approve', [AttendanceEditRequestController::class, 'approve'])
        ->whereNumber('id')->name('requests.approve');
    Route::post('/requests/{id}/reject', [AttendanceEditRequestController::class, 'reject'])
        ->whereNumber('id')->name('requests.reject');
});
