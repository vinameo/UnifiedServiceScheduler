<?php

namespace Tests\Support\Logging;

use App\Support\Logging\ContextProcessor;
use App\Support\Logging\RedactPersonalData;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Trait for test cases asserting on log records (TC-08-01, TC-08-05).
 * Replaces the logger handlers with a Monolog TestHandler while running
 * the production processors (ContextProcessor and RedactPersonalData).
 */
trait CapturesLogs
{
    protected TestHandler $testLogHandler;

    protected function setUpCapturesLogs(): void
    {
        $this->testLogHandler = new TestHandler;

        $monolog = Log::getLogger();
        if ($monolog instanceof MonologLogger) {
            $monolog->setHandlers([$this->testLogHandler]);

            while (! empty($monolog->getProcessors())) {
                $monolog->popProcessor();
            }

            // Monolog runs processors in LIFO order (last pushed runs first)
            $monolog->pushProcessor(new RedactPersonalData);
            $monolog->pushProcessor(new ContextProcessor);
            $monolog->pushProcessor(new PsrLogMessageProcessor);
        }
    }
}
