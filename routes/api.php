<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentAPIController;

Route::middleware('auth')->group(function () {
    Route::get('/agent/businesses', [AgentAPIController::class, 'getAssignedBusinesses']);
    Route::post('/agent/collect', [AgentAPIController::class, 'storeCollection']);
    Route::post('/agent/sync-bulk', [AgentAPIController::class, 'syncBulk']);
});
