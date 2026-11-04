<?php

namespace App\Http\Requests\Booking;

use App\Domain\Auth\Role;
use App\Domain\Booking\CreateBookingInput;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class CreateAppointmentRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->user();
        $isCustomer = $user !== null && $user->role === Role::CUSTOMER;

        $isHold = $this->input('mode') === 'HOLD';

        return [
            'customerId' => $isCustomer ? ['nullable'] : ['required', 'uuid'],
            'dealershipId' => $isCustomer ? ['required', 'uuid'] : ['nullable'],
            'vehicleId' => $isHold ? ['nullable', 'uuid'] : ['required', 'uuid'],
            'serviceTypeId' => ['required', 'uuid'],
            'desiredStart' => ['required', 'string'],
            'reminderOptIn' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:CONFIRM,HOLD'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $desiredStart = $this->input('desiredStart');
            if (! is_string($desiredStart)) {
                return;
            }

            // 1. Regex check: MUST include offset (Z or ±HH:MM)
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/', $desiredStart)) {
                $validator->errors()->add('desiredStart', 'The desired start must be an ISO 8601 string with a timezone offset.');

                return;
            }

            // 2. Validate calendar date (e.g. 2026-02-30)
            $datePart = substr($desiredStart, 0, 10);
            $dateSegments = explode('-', $datePart);
            if (count($dateSegments) === 3) {
                [$year, $month, $day] = $dateSegments;
                if (! checkdate((int) $month, (int) $day, (int) $year)) {
                    $validator->errors()->add('desiredStart', 'The desired start date is invalid.');

                    return;
                }
            }

            // 3. Resolve dealership timezone to validate 15-minute step in local time
            /** @var User|null $user */
            $user = $this->user();
            $isCustomer = $user !== null && $user->role === Role::CUSTOMER;
            $dealershipId = $isCustomer ? $this->input('dealershipId') : $user?->dealership_id;

            if ($dealershipId === null || ! is_string($dealershipId) || ! Str::isUuid($dealershipId)) {
                return; // Handled by dealershipId validation or service
            }

            $dealership = Dealership::find($dealershipId);
            if ($dealership === null) {
                return; // Service will throw DealershipNotFound
            }

            try {
                $localTime = CarbonImmutable::parse($desiredStart)->setTimezone($dealership->timezone);
            } catch (\Throwable) {
                $validator->errors()->add('desiredStart', 'The desired start date could not be parsed.');

                return;
            }

            if ($localTime->second !== 0 || $localTime->minute % 15 !== 0) {
                $validator->errors()->add('desiredStart', 'The desired start time must be on a 15-minute step with seconds equal to 0.');
            }
        });
    }

    public function toInput(): CreateBookingInput
    {
        /** @var User|null $user */
        $user = $this->user();
        $isCustomer = $user !== null && $user->role === Role::CUSTOMER;

        /** @var string $startRaw */
        $startRaw = $this->input('desiredStart');
        $desiredStart = CarbonImmutable::parse($startRaw);

        return new CreateBookingInput(
            dealershipId: $isCustomer ? $this->input('dealershipId') : null,
            customerId: ! $isCustomer ? $this->input('customerId') : null,
            vehicleId: $this->input('vehicleId') !== null ? (string) $this->input('vehicleId') : null,
            serviceTypeId: (string) $this->input('serviceTypeId'),
            desiredStart: $desiredStart,
            reminderOptIn: $this->has('reminderOptIn') ? (bool) $this->input('reminderOptIn') : true,
            mode: (string) ($this->input('mode') ?? 'CONFIRM'),
        );
    }
}
