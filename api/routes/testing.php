<?php

use App\Domain\Booking\Events\AllocationRetried;
use App\Domain\Booking\ResourceType;
use App\Http\Controllers\Api\V1\Testing\TestingAppointmentController;
use App\Http\Controllers\Api\V1\Testing\TestingEchoController;
use Illuminate\Support\Facades\Route;

// Test-only routes for F04 booking integrity (INV-04.14, ADR-04.5).
Route::post('appointments', [TestingAppointmentController::class, 'store'])
    ->middleware(['auth:sanctum', 'idempotent']);

Route::post('appointments/{id}/touch', [TestingAppointmentController::class, 'touch'])
    ->middleware(['auth:sanctum', 'idempotent', 'if-match']);

Route::post('appointments/{id}/transition', [TestingAppointmentController::class, 'transition'])
    ->middleware(['auth:sanctum', 'idempotent', 'if-match']);

Route::post('echo', [TestingEchoController::class, 'handle'])
    ->middleware(['auth:sanctum', 'idempotent']);

Route::get('fail', function () {
    throw new RuntimeException('Deliberate failure with user@example.com and phone 0903123218');
});

Route::post('allocation-retry', function () {
    AllocationRetried::dispatch(
        '01935512-7712-7abc-9def-0123456789ab',
        ResourceType::BAY,
        '01935512-7712-7abc-9def-0123456789ac'
    );

    return response()->json(['status' => 'ok']);
});
