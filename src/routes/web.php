<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('reports')->group(function () {
    Route::get('/stock-levels', [ReportController::class, 'stockLevels'])->name('reports.stock');
    Route::get('/sales-summary', [ReportController::class, 'salesSummary'])->name('reports.sales');
    Route::get('/transfer-summary', [ReportController::class, 'transferSummary'])->name('reports.transfers');
    Route::get('/low-stock', [ReportController::class, 'lowStock'])->name('reports.lowstock');
    Route::get('/audit-logs', [ReportController::class, 'auditLogs'])->name('reports.audit');
});
