<?php

use App\Http\Controllers\Admin\AntiCheatLogController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\HubController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuestionTemplateController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Admin (prefix /admin, name prefix admin.)
|--------------------------------------------------------------------------
|
| Grup ini dibungkus auth + role:admin karena bootstrap hanya menambahkan
| prefix dan name. Tanpa pembungkusan ini seluruh halaman admin terbuka
| untuk siswa yang login.
*/

Route::middleware(['auth', 'role:admin', 'throttle:admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ------------------------------------------------------------------
    // Menu top-level sidebar (hub / overview)
    // ------------------------------------------------------------------

    // Bank soal -> daftar ujian sebagai pintu masuk.
    Route::get('bank-soal', [HubController::class, 'bankSoal'])->name('questions.index');

    // Impor data soal -> daftar ujian sebagai pintu masuk.
    Route::get('import', [HubController::class, 'importSoal'])->name('import.index');

    // Cetak kartu -> daftar ujian sebagai pintu masuk.
    Route::get('kartu', [HubController::class, 'cards'])->name('cards.index');

    // Peserta -> daftar ujian sebagai pintu masuk.
    Route::get('peserta', [HubController::class, 'peserta'])->name('participants.index');

    // Monitoring global (semua attempt berjalan lintas ujian).
    Route::get('monitoring', [HubController::class, 'monitoring'])->name('monitoring.index');

    // Hasil ujian global.
    Route::get('hasil', [HubController::class, 'hasil'])->name('results.index');

    // Log aktivitas (alias ke audit log).
    Route::get('log-aktivitas', [AuditLogController::class, 'index'])->name('logs.index');

    // ------------------------------------------------------------------
    // Bank Soal (batch soal reusable) + impor terpadu + kelola soal
    // ------------------------------------------------------------------
    Route::get('bank', [BankController::class, 'index'])->name('bank.index');
    Route::get('bank/template', [BankController::class, 'template'])->name('bank.template');
    Route::get('bank/impor', [BankController::class, 'create'])->name('bank.create');
    Route::post('bank/impor', [BankController::class, 'store'])
        ->middleware('throttle:import')->name('bank.store');
    Route::get('bank/{batch}', [BankController::class, 'show'])->name('bank.show');
    Route::delete('bank/{batch}', [BankController::class, 'destroy'])->name('bank.destroy');
    Route::get('bank/{batch}/soal/tambah', [BankController::class, 'createQuestion'])->name('bank.questions.create');
    Route::post('bank/{batch}/soal', [BankController::class, 'storeQuestion'])->name('bank.questions.store');
    Route::get('bank/{batch}/soal/{question}/ubah', [BankController::class, 'editQuestion'])->name('bank.questions.edit');
    Route::put('bank/{batch}/soal/{question}', [BankController::class, 'updateQuestion'])->name('bank.questions.update');
    Route::delete('bank/{batch}/soal/{question}', [BankController::class, 'destroyQuestion'])->name('bank.questions.destroy');

    // Manajemen user (admin + siswa)
    Route::get('users/template', [UserController::class, 'template'])->name('users.template');
    Route::post('users/import', [UserController::class, 'import'])
        ->middleware('throttle:import')
        ->name('users.import');
    Route::resource('users', UserController::class)->except(['show']);

    // Manajemen kelas
    Route::get('classes/template', [ClassController::class, 'template'])->name('classes.template');
    Route::post('classes/import', [ClassController::class, 'import'])
        ->middleware('throttle:import')
        ->name('classes.import');
    Route::resource('classes', ClassController::class)->except(['show']);

    // Ujian + aksi transisi status
    Route::resource('exams', ExamController::class);
    Route::post('exams/{exam}/aktifkan', [ExamController::class, 'activate'])->name('exams.activate');
    Route::post('exams/{exam}/jeda', [ExamController::class, 'pause'])->name('exams.pause');
    Route::post('exams/{exam}/lanjutkan', [ExamController::class, 'resume'])->name('exams.resume');
    Route::post('exams/{exam}/tutup', [ExamController::class, 'close'])->name('exams.close');
    Route::post('exams/{exam}/tutup-submit', [ExamController::class, 'closeAndAutoSubmit'])
        ->name('exams.close-auto-submit');

    // Bank soal (per ujian)
    Route::resource('exams.questions', QuestionController::class)->except(['show']);

    // Peserta ujian (token tidak lagi per peserta — kini token sesi per ujian,
    // lihat ExamSessionTokenService; siswa join dengan token dari pengawas)
    Route::resource('exams.participants', ParticipantController::class)->only(['index', 'store', 'destroy']);
    Route::post('exams/{exam}/participants/bulk', [ParticipantController::class, 'bulk'])
        ->name('exams.participants.bulk');

    // Kartu ujian
    Route::get('exams/{exam}/kartu', [CardController::class, 'index'])->name('exams.cards.index');
    Route::get('exams/{exam}/kartu/cetak', [CardController::class, 'print'])->name('exams.cards.print');

    // Monitoring ujian berlangsung (per ujian)
    Route::get('exams/{exam}/monitoring', [MonitoringController::class, 'index'])->name('exams.monitoring.index');
    Route::post('exams/{exam}/monitoring/{attempt}/perpanjang', [MonitoringController::class, 'extend'])
        ->name('exams.monitoring.extend');
    Route::post('exams/{exam}/monitoring/{attempt}/buka', [MonitoringController::class, 'unlock'])
        ->name('exams.monitoring.unlock');
    Route::post('exams/{exam}/monitoring/{attempt}/reset-warning', [MonitoringController::class, 'resetWarnings'])
        ->name('exams.monitoring.reset-warnings');
    Route::post('exams/{exam}/monitoring/{attempt}/submit-paksa', [MonitoringController::class, 'forceSubmit'])
        ->name('exams.monitoring.force-submit');
    Route::post('exams/{exam}/monitoring/{attempt}/reset', [MonitoringController::class, 'resetExam'])
        ->name('exams.monitoring.reset');

    // Log anti-cheat
    Route::get('anti-cheat', [AntiCheatLogController::class, 'index'])->name('anti-cheat.index');
    Route::get('anti-cheat/{attempt}', [AntiCheatLogController::class, 'show'])->name('anti-cheat.show');

    // Template soal (download bawaan + manajemen template tersimpan)
    Route::get('template-soal/unduh/{type}', [QuestionTemplateController::class, 'download'])->name('templates.download');
    Route::resource('templates', QuestionTemplateController::class)->except(['show']);

    // Import soal (per ujian)
    Route::get('exams/{exam}/impor', [ImportController::class, 'show'])->name('exams.import.show');
    Route::post('exams/{exam}/impor/pratinjau', [ImportController::class, 'preview'])
        ->middleware('throttle:import')
        ->name('exams.import.preview');
    Route::post('exams/{exam}/impor/eksekusi', [ImportController::class, 'execute'])
        ->middleware('throttle:import')
        ->name('exams.import.execute');

    // Pengaturan sistem
    Route::get('pengaturan', [SettingController::class, 'index'])->name('settings.index');
    Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');

    // Profil admin yang sedang login (nama tampil + password)
    Route::get('profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profil', [ProfileController::class, 'update'])->name('profile.update');

    // Upload gambar soal (dipakai form edit bank soal & halaman soal ujian)
    Route::post('media/gambar', [MediaController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('media.upload');

    // Audit log (read-only)
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
