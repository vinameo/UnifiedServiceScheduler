<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\ResourceType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ExclusionViolationParser
{
    public static function parse(QueryException $e): ResourceType
    {
        $message = $e->getMessage();
        $previous = $e->getPrevious();
        if ($previous !== null) {
            $message .= ' '.$previous->getMessage();
        }

        // Detail format: Key (resource_type, resource_id, period)=(VEHICLE, 0193...
        if (preg_match('/=\s*\(\s*(TECHNICIAN|BAY|VEHICLE)\s*,/i', $message, $matches)) {
            $matched = strtoupper($matches[1]);

            return match ($matched) {
                'BAY' => ResourceType::BAY,
                'VEHICLE' => ResourceType::VEHICLE,
                default => ResourceType::TECHNICIAN,
            };
        }

        Log::warning('Unable to parse resource_type from exclusion violation; defaulting to TECHNICIAN', [
            'message' => $e->getMessage(),
        ]);

        return ResourceType::TECHNICIAN;
    }
}
