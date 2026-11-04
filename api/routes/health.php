<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Middleware\EnsureInternalMetrics;
use Illuminate\Support\Facades\Route;

// No session, no auth (FR-00.11): load balancers and Docker call these.
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/metrics', [MetricsController::class, 'metrics'])
    ->middleware(EnsureInternalMetrics::class);
