<?php

namespace App\Http\Resources;

use App\Models\ApiEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiEvent
 */
class ApiEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dealership = $this->dealership;
        $tz = $dealership !== null ? $dealership->timezone : (string) config('app.timezone', 'UTC');

        $occurredAt = $this->occurred_at->setTimezone($tz)->toIso8601String();

        return [
            'id' => (int) $this->id,
            'occurredAt' => $occurredAt,
            'endpoint' => $this->resolveEndpoint(),
            'code' => $this->code,
            'status' => $this->status !== null ? (int) $this->status : null,
            'correlationId' => $this->correlation_id,
            'details' => $this->formatDetails($this->detail),
        ];
    }

    private function resolveEndpoint(): string
    {
        $route = (string) $this->route;
        if (str_starts_with($route, $this->method.' ')) {
            $route = substr($route, strlen($this->method) + 1);
        }

        if ($this->method === '-' || $this->method === '') {
            return (string) $this->route;
        }

        $cleanPath = preg_replace('#^/?(?:api/v1/|api/)#', '', $route);

        return $this->method.' /'.ltrim((string) $cleanPath, '/');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function formatDetails(array $detail): string
    {
        if ($this->status === 409 && $this->code === 'NO_AVAILABILITY') {
            $reason = $detail['reason'] ?? 'No availability';
            $start = isset($detail['requestedStart']) ? CarbonImmutable::parse((string) $detail['requestedStart']) : null;
            $end = isset($detail['requestedEnd']) ? CarbonImmutable::parse((string) $detail['requestedEnd']) : null;
            if ($start !== null && $end !== null) {
                $dealership = $this->dealership;
                $tz = $dealership !== null ? $dealership->timezone : (string) config('app.timezone', 'UTC');
                $timeRange = $start->setTimezone($tz)->format('H:i').'–'.$end->setTimezone($tz)->format('H:i');

                return (string) $reason.' '.$timeRange;
            }

            return (string) $reason;
        }

        if ($this->code === 'DELIVERY_FAILED' || isset($detail['event'], $detail['reference'])) {
            $event = $detail['event'] ?? 'Message';
            $reference = $detail['reference'] ?? 'unknown';

            return "{$event} for {$reference}, all 5 attempts used";
        }

        if ($this->status !== null && $this->status >= 500) {
            $class = isset($detail['class']) ? class_basename((string) $detail['class']) : null;
            $message = $detail['message'] ?? 'Internal server error';
            $text = $class !== null ? "{$class}: {$message}" : (string) $message;

            return mb_substr($text, 0, 200);
        }

        if (isset($detail['detail']) && is_string($detail['detail'])) {
            return mb_substr($detail['detail'], 0, 200);
        }
        if (isset($detail['message']) && is_string($detail['message'])) {
            return mb_substr($detail['message'], 0, 200);
        }
        if (isset($detail['title']) && is_string($detail['title'])) {
            return mb_substr($detail['title'], 0, 200);
        }
        if (isset($detail['errors']) && is_array($detail['errors'])) {
            $first = reset($detail['errors']);
            if (is_array($first)) {
                return mb_substr((string) reset($first), 0, 200);
            }
            if (is_string($first)) {
                return mb_substr($first, 0, 200);
            }
        }

        return '';
    }
}
