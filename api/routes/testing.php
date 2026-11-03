<?php

use App\Http\Controllers\Api\V1\Testing\TestingAppointmentController;
use Illuminate\Support\Facades\Route;

// Test-only routes for F04 booking integrity (INV-04.14, ADR-04.5).
Route::post('appointments', [TestingAppointmentController::class, 'store'])
    ->middleware(['auth:sanctum']);

Route::post('appointments/{id}/touch', [TestingAppointmentController::class, 'touch'])
    ->middleware(['auth:sanctum', 'idempotent', 'if-match']);

Route::post('appointments/{id}/transition', [TestingAppointmentController::class, 'transition'])
    ->middleware(['auth:sanctum', 'idempotent', 'if-match']);
