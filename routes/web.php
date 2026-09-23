<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Student\AntiCheatController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\HistoryController;
use App\Http\Controllers\Student\HeartbeatController;
use App\Http\Controllers\Student\JoinController;
use App\Http\Controllers\Student\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Publik & Autentikasi
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $user = request()->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    return $user->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('student.dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.attempt');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Route Siswa (auth + role:siswa)
|--------------------------------------------------------------------------
|
| Seluruh route di grup ini dijamin hanya bisa diakses siswa yang sudah login.
| Endpoint sinkronisasi dan heartbeat diberi throttle longgar karena browser
| mengirim banyak request saat koneksi pulih dari offline.
*/

Route::middleware(['auth', 'role:siswa'])
    ->prefix('siswa')
    ->name('student.')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('ujian', [ExamController::class, 'index'])->name('exams.index');
        Route::get('ujian/{exam}', [ExamController::class, 'show'])->name('exams.show');

        Route::get('ujian/{exam}/ikut', [JoinController::class, 'show'])->name('exam.join');
        Route::post('ujian/{exam}/ikut', [JoinController::class, 'store'])
            ->middleware('throttle:exam-join')
            ->name('exam.join.attempt');

        Route::get('ujian/{exam}/mulai', [ExamController::class, 'run'])->name('exam.run');
        Route::post('ujian/{exam}/mulai', [ExamController::class, 'start'])
            ->name('exam.start');

        Route::post('ujian/{exam}/kumpulkan', [ExamController::class, 'submit'])
            ->name('exam.submit');

        // Endpoint yang dipanggil fetch() oleh outbox offline, harus toleran
        // terhadap burst request saat koneksi pulih.
        Route::post('ujian/{exam}/sync', [SyncController::class, 'store'])
            ->middleware('throttle:exam-sync')
            ->name('exam.sync');

        Route::post('ujian/{exam}/heartbeat', [HeartbeatController::class, 'store'])
            ->middleware('throttle:exam-heartbeat')
            ->name('exam.heartbeat');

        Route::post('ujian/{exam}/anti-cheat', [AntiCheatController::class, 'store'])
            ->middleware('throttle:exam-anti-cheat')
            ->name('exam.anti-cheat');

        Route::get('riwayat', [HistoryController::class, 'index'])->name('history');
        Route::get('riwayat/{attempt}/hasil', [HistoryController::class, 'show'])->name('history.show');
    });
