<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\TestingRoutesServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    TestingRoutesServiceProvider::class,
];
