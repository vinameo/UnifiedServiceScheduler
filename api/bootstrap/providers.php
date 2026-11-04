<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\TestingRoutesServiceProvider;
use App\Providers\TracingServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    TestingRoutesServiceProvider::class,
    TracingServiceProvider::class,
];
