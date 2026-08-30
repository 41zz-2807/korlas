<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RekapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'index'])->name('home');

Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
Route::get('/rekap/export', [RekapController::class, 'export'])->name('rekap.export')
    ->middleware('throttle:public');

Route::get('/transactions/{transaction}/proof', [PublicController::class, 'downloadProof'])
    ->name('transactions.proof')
    ->middleware('throttle:public');

Route::get('/transactions/table', [PublicController::class, 'table'])->name('transactions.table')
    ->middleware('throttle:public');

Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:admin-login');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

Route::middleware([\App\Http\Middleware\EnsureAdmin::class, 'throttle:admin'])->group(function () {
    Route::get('/admin/transactions/create', [AdminController::class, 'create'])->name('admin.transactions.create');
    Route::post('/admin/transactions', [AdminController::class, 'store'])->name('admin.transactions.store');
    Route::patch('/transactions/{transaction}', [AdminController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [AdminController::class, 'destroy'])->name('transactions.destroy');
    Route::post('/transactions/bulk-delete', [AdminController::class, 'bulkDestroy'])->name('transactions.bulkDestroy');

    Route::get('/admin/backup', [AdminController::class, 'backupIndex'])->name('admin.backup');
    Route::get('/admin/backup/db', [AdminController::class, 'backupDatabase'])->name('admin.backup.db');
    Route::get('/admin/backup/uploads', [AdminController::class, 'backupUploads'])->name('admin.backup.uploads');
    Route::post('/admin/restore/db', [AdminController::class, 'restoreDatabase'])->name('admin.restore.db');

    Route::post('/admin/report/telegram', [AdminController::class, 'sendTelegramReport'])->name('admin.report.telegram');
});
