<?php

namespace App\Support\Problem;

use App\Http\Middleware\CorrelationId;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An RFC 9457 problem details body. Clients act on `code`, never on `title` or `detail`.
 */
final class Problem implements Responsable
{
    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * @param  array<string, mixed>  $extra  More members, for example `errors` or `alternatives`.
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $code,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly array $extra = [],
        public readonly array $headers = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $correlationId = null): array
    {
        return array_filter([
            'type' => '/problems/'.strtolower(str_replace('_', '-', $this->code)),
            'title' => $this->title,
            'status' => $this->status,
            'code' => $this->code,
            'detail' => $this->detail,
            'correlationId' => $correlationId,
            ...$this->extra,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(CorrelationId::of($request)),
            $this->status,
            ['Content-Type' => self::CONTENT_TYPE, ...$this->headers],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
