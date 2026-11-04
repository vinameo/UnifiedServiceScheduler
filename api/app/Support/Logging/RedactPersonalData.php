<?php

namespace App\Support\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips personal data (emails, phone numbers, passwords, tokens, secrets, cookies)
 * from Monolog log records and arbitrary context/strings (D-08.2).
 *
 * Reused as a shared seam in F11 (outbox last_error) and api_events.detail.
 */
final class RedactPersonalData implements ProcessorInterface
{
    private const SENSITIVE_KEY_PATTERN = '/password|token|secret|authorization|cookie|phone|email/i';

    private const PHONE_PATTERN = '/(?<![\w-])\+?\d(?:[ .-]?\d){8,14}(?![\w-])/';

    private const EMAIL_PATTERN = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+/';

    /**
     * @var list<string>
     */
    private const PRESERVED_EXACT_KEYS = [
        'correlationid',
        'reference',
        'route',
        'occurredat',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::redactString($record->message),
            context: self::redactArray($record->context),
            extra: self::redactArray($record->extra),
        );
    }

    /**
     * Redacts email-like and phone-like patterns from a string.
     */
    public static function redactString(string $text): string
    {
        $text = (string) preg_replace(self::EMAIL_PATTERN, '[email]', $text);

        return (string) preg_replace(self::PHONE_PATTERN, '[phone]', $text);
    }

    /**
     * Alias for redactString.
     */
    public static function redact(string $text): string
    {
        return self::redactString($text);
    }

    /**
     * Redacts an array by dropping sensitive keys and redacting string values
     * (except for preserved keys like *Id, *_id, correlationId, etc.).
     *
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function redactArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $keyStr = (string) $key;

            if (preg_match(self::SENSITIVE_KEY_PATTERN, $keyStr) === 1) {
                // Drop context key matching password|token|secret|authorization|cookie|phone|email
                continue;
            }

            if (is_array($value)) {
                $result[$key] = self::redactArray($value);
            } elseif (is_string($value)) {
                if (self::isPreservedKey($keyStr)) {
                    $result[$key] = $value;
                } else {
                    $result[$key] = self::redactString($value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Alias for redactArray.
     *
     * @param  array<string|int, mixed>  $context
     * @return array<string|int, mixed>
     */
    public static function redactContext(array $context): array
    {
        return self::redactArray($context);
    }

    /**
     * Values of keys ending in Id/_id, plus correlationId, reference, route, and occurredAt,
     * are never rewritten.
     */
    public static function isPreservedKey(string $key): bool
    {
        if (strcasecmp($key, 'id') === 0 || str_ends_with($key, 'Id') || str_ends_with($key, '_id')) {
            return true;
        }

        return in_array(strtolower($key), self::PRESERVED_EXACT_KEYS, true);
    }
}
