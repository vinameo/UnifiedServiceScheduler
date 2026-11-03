<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
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
            'serviceTypeId' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'excludeAppointmentId' => ['sometimes', 'uuid'],
        ];
    }

    public function serviceTypeId(): string
    {
        return (string) $this->query('serviceTypeId');
    }

    public function dateString(): string
    {
        return (string) $this->query('date');
    }

    public function excludeAppointmentId(): ?string
    {
        return $this->filled('excludeAppointmentId') ? (string) $this->query('excludeAppointmentId') : null;
    }
}
