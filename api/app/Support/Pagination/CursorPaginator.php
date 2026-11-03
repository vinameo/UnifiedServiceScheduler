<?php

namespace App\Support\Pagination;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use JsonException;

class CursorPaginator
{
    /**
     * Encode cursor payload into an opaque base64url string.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $normalized[$key] = $value->format('Y-m-d\TH:i:s.uP');
            } else {
                $normalized[$key] = $value;
            }
        }

        try {
            $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid.'],
            ]);
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Decode opaque cursor string into an array of values.
     *
     * @param  list<string>  $expectedKeys
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function decode(string $cursor, array $expectedKeys = []): array
    {
        $trimmed = trim($cursor);
        if ($trimmed === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $trimmed)) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid.'],
            ]);
        }

        $remainder = strlen($trimmed) % 4;
        $padded = $remainder === 0 ? $trimmed : $trimmed.str_repeat('=', 4 - $remainder);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid.'],
            ]);
        }

        // Try JSON decode first
        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data)) {
                foreach ($expectedKeys as $key) {
                    if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                        throw ValidationException::withMessages([
                            'cursor' => ['The cursor is invalid.'],
                        ]);
                    }
                }

                return $data;
            }
        } catch (JsonException) {
            // Fall back to pipe-separated format (e.g. start_at|id)
        }

        if (str_contains($decoded, '|')) {
            $parts = explode('|', $decoded);
            if (! empty($expectedKeys) && count($parts) === count($expectedKeys)) {
                $mapped = array_combine($expectedKeys, $parts);
                foreach ($mapped as $val) {
                    if ($val === '') {
                        throw ValidationException::withMessages([
                            'cursor' => ['The cursor is invalid.'],
                        ]);
                    }
                }

                return $mapped;
            }
        }

        throw ValidationException::withMessages([
            'cursor' => ['The cursor is invalid.'],
        ]);
    }
}
