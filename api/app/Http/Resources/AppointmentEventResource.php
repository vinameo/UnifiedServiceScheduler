<?php

namespace App\Http\Resources;

use App\Models\AppointmentEvent;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppointmentEvent
 */
class AppointmentEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dealership = $this->dealership;
        $tzName = $dealership !== null ? $dealership->timezone : 'UTC';
        $tz = new DateTimeZone($tzName);
        $occurredAt = CarbonImmutable::parse($this->occurred_at)->setTimezone($tz)->toIso8601String();

        $actor = null;
        if ($this->actor_user_id !== null || $this->actorUser !== null) {
            $user = $this->actorUser;
            $actor = [
                'name' => $user !== null ? $user->name : 'System',
                'role' => $this->actor_role ?? ($user?->role instanceof \BackedEnum ? $user->role->value : (string) $user?->role),
            ];
        }

        $rawData = $this->data;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'fromStatus' => $this->from_status,
            'toStatus' => $this->to_status,
            'version' => $this->version,
            'actor' => $actor,
            'data' => $this->sanitizeData($rawData),
            'occurredAt' => $occurredAt,
        ];
    }

    /**
     * Sanitize data payload to ensure no personal data (phone, email) leaks (INV-05.7, DB-04.4).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        $disallowedPatterns = ['phone', 'email'];

        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);
            $containsDisallowed = false;
            foreach ($disallowedPatterns as $pattern) {
                if (str_contains($keyLower, $pattern)) {
                    $containsDisallowed = true;
                    break;
                }
            }

            if ($containsDisallowed) {
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
