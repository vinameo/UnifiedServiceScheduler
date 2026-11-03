<?php

namespace App\Http;

use App\Domain\Booking\Exceptions\PreconditionRequired;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ExpectedVersion
{
    public static function from(Request $request): int
    {
        $version = $request->attributes->get('expectedVersion');
        if ($version !== null) {
            return (int) $version;
        }

        $header = $request->header('If-Match');
        if ($header === null || $header === '') {
            throw new PreconditionRequired;
        }

        return self::parse($header);
    }

    public static function parse(string $header): int
    {
        if (! preg_match('/^"([1-9]\d*)"$/', trim($header), $matches)) {
            throw ValidationException::withMessages([
                'If-Match' => ['The If-Match header must be a quoted positive integer (e.g. "1").'],
            ]);
        }

        return (int) $matches[1];
    }
}
