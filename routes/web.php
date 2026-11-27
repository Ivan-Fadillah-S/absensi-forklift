<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\TimesheetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\AreaController;

// Pintu utama: arahkan sesuai peran
Route::get('/', function () {
    $user = Auth::user();

    if (! $user) {
        return redirect()->route('login');
    }

    return redirect()->route(
        $user->isMonitoring() ? 'monitoring.dashboard' : 'operator.absen'
    );
});

// Hanya untuk yang belum login
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// Hanya untuk yang sudah login
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Operator
    Route::middleware('role:operator')->group(function () {
        Route::get('/absen', [AttendanceController::class, 'show'])->name('operator.absen');
        Route::post('/absen/{type}', [AttendanceController::class, 'store'])
            ->whereIn('type', ['masuk', 'pulang'])
            ->name('operator.absen.store');

        Route::get('/timesheet', [TimesheetController::class, 'index'])->name('operator.timesheet');
        Route::post('/timesheet', [TimesheetController::class, 'store'])->name('operator.timesheet.store');
        Route::post('/timesheet/keterangan', [TimesheetController::class, 'setStatus'])->name('operator.timesheet.status');
        Route::delete('/timesheet/keterangan', [TimesheetController::class, 'clearStatus'])->name('operator.timesheet.status.clear');
        Route::delete('/timesheet/{entry}', [TimesheetController::class, 'destroy'])
            ->whereNumber('entry')
            ->name('operator.timesheet.destroy');

        Route::get('/riwayat', [RiwayatController::class, 'index'])->name('operator.riwayat');
    });

        // Monitoring
    Route::middleware('role:monitoring')->group(function () {
        Route::get('/dashboard', [MonitoringController::class, 'dashboard'])->name('monitoring.dashboard');

        Route::get('/monitoring/absensi/{operator}/{tanggal}', [MonitoringController::class, 'show'])
            ->where('tanggal', '\d{4}-\d{2}-\d{2}')
            ->name('monitoring.attendance.show');

        Route::put('/monitoring/absensi/{operator}/{tanggal}', [MonitoringController::class, 'update'])
            ->where('tanggal', '\d{4}-\d{2}-\d{2}')
            ->name('monitoring.attendance.update');
        
        Route::get('/monitoring/rekap', [RekapController::class, 'index'])->name('monitoring.rekap');
        Route::get('/monitoring/rekap/unduh', [RekapController::class, 'export'])->name('monitoring.rekap.export');
        Route::get('/monitoring/rekap/{operator}', [RekapController::class, 'harian'])
            ->whereNumber('operator')
            ->name('monitoring.rekap.harian');

        Route::get('/monitoring/ai', [AiController::class, 'index'])->name('monitoring.ai');
        Route::post('/monitoring/ai/koreksi', [AiController::class, 'koreksi'])->name('monitoring.ai.koreksi');
        Route::post('/monitoring/ai/ringkas', [AiController::class, 'ringkas'])->name('monitoring.ai.ringkas');
                Route::get('/monitoring/operator', [OperatorController::class, 'index'])->name('monitoring.operator.index');
        Route::get('/monitoring/operator/tambah', [OperatorController::class, 'create'])->name('monitoring.operator.create');
        Route::post('/monitoring/operator', [OperatorController::class, 'store'])->name('monitoring.operator.store');
        Route::get('/monitoring/operator/{operator}/ubah', [OperatorController::class, 'edit'])
            ->whereNumber('operator')->name('monitoring.operator.edit');
        Route::put('/monitoring/operator/{operator}', [OperatorController::class, 'update'])
            ->whereNumber('operator')->name('monitoring.operator.update');
        Route::patch('/monitoring/operator/{operator}/status', [OperatorController::class, 'toggle'])
            ->whereNumber('operator')->name('monitoring.operator.toggle');

                Route::get('/monitoring/area', [AreaController::class, 'show'])->name('monitoring.area');
        Route::put('/monitoring/area', [AreaController::class, 'update'])->name('monitoring.area.update');
    });
});