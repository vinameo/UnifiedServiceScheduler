<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\AssertsNoOverlap;

abstract class TestCase extends BaseTestCase
{
    use AssertsNoOverlap;
}
