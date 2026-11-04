<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'newStart' => ['required', 'string'],
            'keepTechnician' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $newStart = $this->input('newStart');
            if (! is_string($newStart)) {
                return;
            }

            // 1. Regex check: MUST include offset (Z or ±HH:MM)
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/', $newStart)) {
                $validator->errors()->add('newStart', 'The new start must be an ISO 8601 string with a timezone offset.');

                return;
            }

            // 2. Validate calendar date (e.g. 2026-02-30)
            $datePart = substr($newStart, 0, 10);
            $dateSegments = explode('-', $datePart);
            if (count($dateSegments) === 3) {
                [$year, $month, $day] = $dateSegments;
                if (! checkdate((int) $month, (int) $day, (int) $year)) {
                    $validator->errors()->add('newStart', 'The new start date is invalid.');

                    return;
                }
            }

            // 3. Resolve dealership timezone to validate 15-minute step in local time
            $appointment = $this->route('appointment');
            $dealership = null;
            if ($appointment instanceof Appointment) {
                $dealership = $appointment->dealership ?? Dealership::find($appointment->dealership_id);
            } elseif (is_string($appointment)) {
                $apptModel = Appointment::find($appointment);
                if ($apptModel !== null) {
                    $dealership = $apptModel->dealership ?? Dealership::find($apptModel->dealership_id);
                }
            }

            if ($dealership === null) {
                return;
            }

            try {
                $localTime = CarbonImmutable::parse($newStart)->setTimezone($dealership->timezone);
            } catch (\Throwable) {
                $validator->errors()->add('newStart', 'The new start date could not be parsed.');

                return;
            }

            if ($localTime->second !== 0 || $localTime->minute % 15 !== 0) {
                $validator->errors()->add('newStart', 'The new start time must be on a 15-minute step with seconds equal to 0.');
            }
        });
    }

    public function newStart(): CarbonImmutable
    {
        /** @var string $startRaw */
        $startRaw = $this->input('newStart');

        return CarbonImmutable::parse($startRaw);
    }

    public function keepTechnician(): bool
    {
        if ($this->has('keepTechnician') && $this->input('keepTechnician') !== null) {
            return $this->boolean('keepTechnician');
        }

        return true;
    }
}
