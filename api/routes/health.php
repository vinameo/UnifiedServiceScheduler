<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// No session, no auth (FR-00.11): load balancers and Docker call these.
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
