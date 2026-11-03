<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class NextAvailableRequest extends FormRequest
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
            'after' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $after = $this->query('after');
            if (! is_string($after)) {
                return;
            }

            // Must include timezone offset (Z or ±HH:MM)
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/', $after)) {
                $validator->errors()->add('after', 'The after must be an ISO 8601 string with a timezone offset.');

                return;
            }

            $datePart = substr($after, 0, 10);
            $dateSegments = explode('-', $datePart);
            if (count($dateSegments) === 3) {
                [$year, $month, $day] = $dateSegments;
                if (! checkdate((int) $month, (int) $day, (int) $year)) {
                    $validator->errors()->add('after', 'The after date is invalid.');

                    return;
                }
            }

            try {
                CarbonImmutable::parse($after);
            } catch (\Throwable) {
                $validator->errors()->add('after', 'The after date is invalid.');
            }
        });
    }

    public function serviceTypeId(): string
    {
        return (string) $this->query('serviceTypeId');
    }

    public function afterDateTime(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->query('after'));
    }
}
