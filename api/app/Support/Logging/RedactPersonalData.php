<?php

namespace App\Support\Logging;

use JsonSerializable;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Strips personal data (emails, phone numbers, passwords, tokens, secrets, cookies, sessions)
 * from Monolog log records, Throwables, and arbitrary context/strings (D-08.2).
 *
 * Reused as a shared seam in F11 (outbox last_error) and api_events.detail.
 */
final class RedactPersonalData implements ProcessorInterface
{
    private const SENSITIVE_KEY_PATTERN = '/password|token|secret|authorization|cookie|phone|email|session|xsrf/i';

    private const PHONE_PATTERN = '/(?<![\w-])\+?\d(?:[ .-]?\d){8,14}(?![\w-])/';

    private const EMAIL_PATTERN = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+/';

    private const COOKIE_TOKEN_PATTERN = '/(?:XSRF(?:-TOKEN)?|laravel_session)(?:=[^;\s]*)?/i';

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
     * Redacts email-like, phone-like, and cookie/session patterns from a string.
     */
    public static function redactString(string $text): string
    {
        $text = (string) preg_replace(self::EMAIL_PATTERN, '[email]', $text);
        $text = (string) preg_replace(self::PHONE_PATTERN, '[phone]', $text);

        return (string) preg_replace(self::COOKIE_TOKEN_PATTERN, '[cookie]', $text);
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
                // Drop context key matching password|token|secret|authorization|cookie|phone|email|session|xsrf
                continue;
            }

            if ($value instanceof Throwable) {
                $result[$key] = self::redactThrowable($value);
            } elseif (is_array($value)) {
                $result[$key] = self::redactArray($value);
            } elseif ($value instanceof JsonSerializable) {
                $serialized = $value->jsonSerialize();
                $result[$key] = is_array($serialized) ? self::redactArray($serialized) : $serialized;
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
     * Normalizes and redacts a Throwable instance so exception messages
     * never leak personal data.
     *
     * @return array<string, mixed>
     */
    public static function redactThrowable(Throwable $e): array
    {
        $normalized = [
            'class' => get_class($e),
            'message' => self::redactString($e->getMessage()),
            'code' => $e->getCode(),
            'file' => $e->getFile().':'.$e->getLine(),
        ];

        if ($e->getPrevious() !== null) {
            $normalized['previous'] = self::redactThrowable($e->getPrevious());
        }

        return $normalized;
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
