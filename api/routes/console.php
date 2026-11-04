<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('idempotency:prune')->daily();
Schedule::command('monitoring:prune')->daily();
Schedule::command('holds:expire')->everyThirtySeconds()->withoutOverlapping();
