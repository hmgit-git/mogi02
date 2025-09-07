<?php

use Illuminate\Support\Facades\Route;

// 一般ユーザー側
use App\Http\Controllers\Attendances\AttendanceController;
use App\Http\Controllers\User\EditRequestListController;

// 管理者側
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AttendanceEditRequestController;
use App\Http\Controllers\Admin\AdminStaffController;

/**
 * 未ログイン（一般ユーザー）専用：画面だけ
 * Fortify/Breeze 等の POST は既定ルートを利用
 */
Route::middleware('guest:web')->group(function () {
    Route::view('/login', 'auth.login')->name('login');          // GET /login
    Route::view('/register', 'auth.register')->name('register'); // GET /register
});

/** メール認証案内（ログインは必要・verified は不要） */
Route::get('/email/verify', fn() => view('auth.verify-email'))
    ->middleware('auth')
    ->name('verification.notice');

/** 任意：ダッシュボード（ログイン＋認証済み） */
Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/** 勤怠（ログイン＋認証済み） */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

    Route::post('/attendance/in',          [AttendanceController::class, 'punchIn'])->name('attendance.punch.in');
    Route::post('/attendance/break/start', [AttendanceController::class, 'breakStart'])->name('attendance.break.start');
    Route::post('/attendance/break/end',   [AttendanceController::class, 'breakEnd'])->name('attendance.break.end');
    Route::post('/attendance/out',         [AttendanceController::class, 'punchOut'])->name('attendance.punch.out');

    Route::get('/attendance/list',         [AttendanceController::class, 'list'])->name('attendance.list');
    Route::get('/attendance/detail/{id}',  [AttendanceController::class, 'detail'])->name('attendance.detail');

    Route::get('/attendance/detail/date/{date}', [AttendanceController::class, 'detailByDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendance.detail.date');

    // 修正申請の送信
    Route::post('/attendance/detail/request', [AttendanceController::class, 'requestUpdate'])
        ->name('attendance.request');

    Route::get('/my/requests/pending',  [EditRequestListController::class, 'pending'])->name('my.requests.pending');
    Route::get('/my/requests/approved', [EditRequestListController::class, 'approved'])->name('my.requests.approved');
    Route::get('/my/requests/{id}',     [EditRequestListController::class, 'show'])->name('my.requests.show');
});

/** トップは勤怠へ（未ログインなら /login へ誘導される） */
Route::redirect('/', '/attendance');

/** 管理者 */
Route::prefix('admin')->name('admin.')->group(function () {

    // --- 未ログイン時（管理者ログイン） ---
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login.form');
        Route::post('/login', [AdminLoginController::class, 'login'])
            ->middleware('throttle:6,1')
            ->name('login');
    });

    // --- 管理者ログイン後 ---
    Route::middleware('auth:admin')->group(function () {
        // ダッシュボード & 勤怠
        Route::get('/dashboard', [AdminAttendanceController::class, 'daily'])->name('dashboard');
        Route::get('/attendances', [AdminAttendanceController::class, 'daily'])->name('attendances.daily');
        Route::get('/attendances/{id}', [AdminAttendanceController::class, 'show'])->name('attendances.show');
        Route::put('/attendances/{id}', [AdminAttendanceController::class, 'update'])->name('attendances.update');

        // 申請
        Route::get('/requests', [AttendanceEditRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{id}', [AttendanceEditRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{id}/approve', [AttendanceEditRequestController::class, 'approve'])->name('requests.approve');

        // スタッフ一覧 → 個別の月次勤怠
        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/{user}/attendances', [AdminStaffController::class, 'monthly'])->name('staff.attendances');
        Route::get('/staff/{user}/attendances/csv', [AdminStaffController::class, 'exportCsv'])->name('staff.attendances.csv');

        // ログアウト（POST）
        Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');
    });
});
