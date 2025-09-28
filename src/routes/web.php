<?php

use Illuminate\Support\Facades\Route;

// 一般ユーザー側
use App\Http\Controllers\Attendances\AttendanceController;
use App\Http\Controllers\User\EditRequestListController;

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
    Route::get('/attendance/detail/{id}',  [AttendanceController::class, 'detail'])
        ->whereNumber('id')->name('attendance.detail');

    Route::get('/attendance/detail/date/{date}', [AttendanceController::class, 'detailByDate'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendance.detail.date');

    // 修正申請の送信
    Route::post('/attendance/detail/request', [AttendanceController::class, 'requestUpdate'])
        ->name('attendance.request');

    // 自分の申請一覧
    Route::redirect('/my/requests', '/my/requests/pending')->name('my.requests.index');
    Route::get('/my/requests/pending',  [EditRequestListController::class, 'pending'])->name('my.requests.pending');
    Route::get('/my/requests/approved', [EditRequestListController::class, 'approved'])->name('my.requests.approved');
    Route::get('/my/requests/{id}',     [EditRequestListController::class, 'show'])
        ->whereNumber('id')->name('my.requests.show');
});

/** トップは勤怠へ（未ログインなら /login へ誘導される） */
Route::redirect('/', '/attendance');

// ===============================
// ▼ 共用化ルート（PG05 / PG09, PG06 / PG12）
// ===============================
Route::middleware('auth.any')->group(function () {
    // 勤怠詳細 (PG05: user, PG09: admin) → guard に応じてリダイレクト
    Route::get('/attendance/{id}', function ($id) {
        if (auth('admin')->check()) {
            return redirect()->route('admin.attendances.show', ['id' => $id]);
        }
        return redirect()->route('attendance.detail', ['id' => $id]);
    })->whereNumber('id')->name('attendance.show.shared');

    // 申請一覧 (PG06: user, PG12: admin) → guard に応じてリダイレクト
    Route::get('/stamp_correction_request/list', function () {
        if (auth('admin')->check()) {
            return redirect()->route('admin.requests.index');
        }
        return redirect()->route('my.requests.pending');
    })->name('requests.list.shared');
});
