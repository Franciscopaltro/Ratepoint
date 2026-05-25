<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        
        Route::prefix('reconciliation')->group(function () {
            Route::get('/', [ReconciliationController::class, 'index'])->name('admin.reconciliation.index');
            Route::post('/store', [ReconciliationController::class, 'store'])->name('admin.reconciliation.store');
        });

        Route::get('/agents', [AgentController::class, 'index'])->name('admin.agents.index');
        Route::post('/agents/store', [AgentController::class, 'store'])->name('admin.agents.store');
        
        Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
        Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');

        Route::get('/collections', [CollectionController::class, 'index'])->name('admin.collections.index');
        Route::get('/businesses', function() { 
            $businesses = \App\Models\Business::with('zone')->get();
            return view('admin.businesses', compact('businesses')); 
        });
    });

    Route::get('/agent', function() {
        return view('agent.index');
    })->name('agent.dashboard');
});
