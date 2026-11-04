<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BayController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DealershipAppointmentController;
use App\Http\Controllers\Api\V1\DealershipController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MonitoringController;
use App\Http\Controllers\Api\V1\MyAppointmentController;
use App\Http\Controllers\Api\V1\OutboxController;
use App\Http\Controllers\Api\V1\ServiceTypeController;
use App\Http\Controllers\Api\V1\TechnicianController;
use App\Http\Controllers\Api\V1\WorkshopActionController;
use App\Http\Controllers\Api\V1\WorkshopBoardController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('auth-ip', function (Request $request) {
    return Limit::perMinute(30)->by($request->ip());
});

Route::post('/auth/customer/sign-up', [AuthController::class, 'customerSignUp'])
    ->middleware('throttle:auth-ip');

Route::post('/auth/customer/sign-in', [AuthController::class, 'customerSignIn'])
    ->middleware('throttle:auth-ip');

Route::post('/auth/staff/sign-in', [AuthController::class, 'staffSignIn'])
    ->middleware('throttle:auth-ip');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/sign-out', [AuthController::class, 'signOut']);
    Route::get('/me', [MeController::class, 'me']);
    Route::get('/me/vehicles', [MeController::class, 'vehicles'])->middleware('can:customer');
    Route::post('/me/vehicles', [MeController::class, 'addVehicle'])->middleware(['can:customer', 'idempotent']);
    Route::get('/me/appointments', [MyAppointmentController::class, 'index'])->middleware('can:customer');

    // Master data (F01)
    Route::get('/dealerships', [DealershipController::class, 'index']);
    Route::get('/dealerships/{dealership}', [DealershipController::class, 'show']);
    Route::get('/dealerships/{dealership}/service-types', [ServiceTypeController::class, 'index']);
    Route::get('/dealerships/{dealership}/bays', [BayController::class, 'index'])->middleware('can:staff');
    Route::get('/dealerships/{dealership}/technicians', [TechnicianController::class, 'index'])->middleware('can:staff');
    Route::put('/dealerships/{dealership}/technicians/{technician}/status', [TechnicianController::class, 'updateStatus'])->middleware('can:admin');

    // Booking (F02, F05, F07, F09, F10)
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware(['can:create-appointment', 'idempotent']);
    Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])
        ->middleware(['can:confirm-hold,appointment', 'idempotent', 'if-match']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
        ->middleware(['can:cancel-appointment', 'idempotent', 'if-match']);
    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
        ->middleware(['can:reschedule-appointment,appointment', 'idempotent', 'if-match']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
        ->middleware('can:view,appointment');
    Route::get('/appointments/{appointment}/history', [AppointmentController::class, 'history'])
        ->middleware(['can:staff', 'can:view,appointment']);
    Route::get('/dealerships/{dealership}/appointments', [DealershipAppointmentController::class, 'index'])
        ->middleware('can:staff');

    // Workshop actions (F12)
    Route::get('/dealerships/{dealership}/workshop-board', [WorkshopBoardController::class, 'show'])
        ->middleware('can:view-board');
    Route::post('/appointments/{appointment}/check-in', [WorkshopActionController::class, 'checkIn'])
        ->middleware(['can:check-in', 'idempotent', 'if-match']);
    Route::post('/appointments/{appointment}/start', [WorkshopActionController::class, 'start'])
        ->middleware(['can:run-workshop', 'idempotent', 'if-match']);
    Route::post('/appointments/{appointment}/complete', [WorkshopActionController::class, 'complete'])
        ->middleware(['can:run-workshop', 'idempotent', 'if-match']);
    Route::post('/appointments/{appointment}/no-show', [WorkshopActionController::class, 'noShow'])
        ->middleware(['can:check-in', 'idempotent', 'if-match']);
    Route::post('/appointments/{appointment}/extend', [WorkshopActionController::class, 'extend'])
        ->middleware(['can:run-workshop', 'idempotent', 'if-match']);

    // Customers (F02)
    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware(['can:search-customers', 'throttle:60,1']);
    Route::get('/customers/{customer}/vehicles', [CustomerController::class, 'vehicles'])
        ->middleware('can:search-customers');

    // Availability (F06)
    Route::get('/dealerships/{dealership}/availability/next', [AvailabilityController::class, 'next'])
        ->middleware('throttle:120,1');
    Route::get('/dealerships/{dealership}/availability', [AvailabilityController::class, 'availability'])
        ->middleware('throttle:120,1');

    // Monitoring (F08)
    Route::get('/monitoring/summary', [MonitoringController::class, 'summary'])
        ->middleware('can:admin');
    Route::get('/monitoring/errors', [MonitoringController::class, 'errors'])
        ->middleware('can:admin');

    // Notifications outbox (F11)
    Route::get('/outbox', [OutboxController::class, 'index'])
        ->middleware('can:admin');
    Route::get('/outbox/summary', [OutboxController::class, 'summary'])
        ->middleware('can:admin');
    Route::post('/outbox/{id}/retry', [OutboxController::class, 'retry'])
        ->middleware(['can:admin', 'idempotent']);
});
