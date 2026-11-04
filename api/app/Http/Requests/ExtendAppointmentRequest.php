<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExtendAppointmentRequest extends FormRequest
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
            'minutes' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $minutes = $this->input('minutes');

            if (! is_int($minutes)) {
                $validator->errors()->add('minutes', 'The minutes field must be an integer.');

                return;
            }

            if (! in_array($minutes, [15, 30, 45, 60, 90, 120], true)) {
                $validator->errors()->add('minutes', 'The minutes must be one of: 15, 30, 45, 60, 90, 120.');
            }
        });
    }

    public function minutes(): int
    {
        /** @var int $minutes */
        $minutes = $this->input('minutes');

        return $minutes;
    }
}
