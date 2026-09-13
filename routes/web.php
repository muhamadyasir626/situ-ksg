<?php

use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

// Route::view('/', 'welcome')->name('home');
Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages.dashboard')->name('dashboard');
    Route::livewire('realisasi', 'pages.realisasi-list')->name('realisasi');
    Route::livewire('realisasi/create', 'pages.report-form')->name('realisasi.create');
    Route::livewire('realisasi/{report}/edit', 'pages.report-form')->name('realisasi.edit');
    Route::livewire('verifikasi', 'pages.verifikasi-list')->name('verifikasi');
    Route::livewire('risiko', 'pages.risiko-list')->name('risiko');
    Route::livewire('laporan', 'pages.laporan')->name('laporan');
    Route::get('laporan/ekspor/kinerja.xlsx', [ReportExportController::class, 'performanceExcel'])->name('laporan.export.performance-excel');
    Route::get('laporan/ekspor/kinerja.pdf', [ReportExportController::class, 'performancePdf'])->name('laporan.export.performance-pdf');
    Route::get('laporan/ekspor/risiko.xlsx', [ReportExportController::class, 'riskRegisterExcel'])->name('laporan.export.risk-excel');
    Route::get('laporan/ekspor/realisasi-unit.xlsx', [ReportExportController::class, 'unitRealizationExcel'])->name('laporan.export.unit-excel');
    Route::get('laporan/ekspor/bulanan.xlsx', [ReportExportController::class, 'monthlyReportExcel'])->name('laporan.export.monthly-excel');
    Route::livewire('indikator', 'pages.indikator')->name('indikator');
    Route::livewire('audit-trail', 'pages.audit-trail')->name('audit-trail');

    // Fase 21: pagu anggaran kini dikelola per-periode di Manajemen Indikator.
    // /pagu dialihkan ke sana agar bookmark lama tetap berfungsi.
    Route::redirect('pagu', '/admin/indicators');

    Route::middleware(['role:Admin'])->group(function () {
        Route::livewire('admin/users', 'admin.manage-users')->name('admin.users');
        Route::livewire('admin/units', 'admin.manage-units')->name('admin.units');
        Route::livewire('admin/periods', 'admin.manage-periods')->name('admin.periods');
        Route::livewire('admin/indicators', 'admin.manage-indicators')->name('admin.indicators');
        Route::livewire('admin/assignments', 'admin.manage-assignments')->name('admin.assignments');
    });
});

require __DIR__.'/settings.php';
