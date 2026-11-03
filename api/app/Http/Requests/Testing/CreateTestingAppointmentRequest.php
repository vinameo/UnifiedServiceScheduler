<?php

namespace App\Http\Requests\Testing;

use Illuminate\Foundation\Http\FormRequest;

class CreateTestingAppointmentRequest extends FormRequest
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
            'dealershipId' => ['required', 'uuid'],
            'customerId' => ['required', 'uuid'],
            'vehicleId' => ['nullable', 'uuid'],
            'serviceTypeId' => ['required', 'uuid'],
            'start' => ['required', 'date'],
        ];
    }
}
