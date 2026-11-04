<?php

namespace App\Http\Requests;

use App\Models\Dealership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkshopBoardRequest extends FormRequest
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
            'date' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $date = $this->query('date');
            if ($date === null || $date === '' || $date === 'today') {
                return;
            }

            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $validator->errors()->add('date', 'The date is invalid.');

                return;
            }

            [$year, $month, $day] = explode('-', $date);
            if (! checkdate((int) $month, (int) $day, (int) $year)) {
                $validator->errors()->add('date', 'The date is invalid.');
            }
        });
    }

    public function targetDate(Dealership $dealership, CarbonImmutable $now): string
    {
        $date = $this->query('date');
        if ($date === null || $date === '' || $date === 'today') {
            return $now->setTimezone($dealership->timezone ?: 'UTC')->toDateString();
        }

        return (string) $date;
    }
}
