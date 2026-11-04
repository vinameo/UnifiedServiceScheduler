<?php

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Monolog tap that attaches the standard processors in execution order:
 * 1. PsrLogMessageProcessor (resolves message placeholders)
 * 2. ContextProcessor (injects correlationId, userId, dealershipId, appointmentId, outcome)
 * 3. RedactPersonalData (removes sensitive data from message and context)
 */
final class ConfigureLogging
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            // Monolog executes processors in LIFO order (last pushed runs first).
            $monolog->pushProcessor(new RedactPersonalData);
            $monolog->pushProcessor(new ContextProcessor);
            $monolog->pushProcessor(new PsrLogMessageProcessor);
        }
    }
}
