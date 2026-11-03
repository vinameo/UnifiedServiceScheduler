<?php

namespace App\Http\Requests\Vehicles;

use App\Models\Vehicle;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AddVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'licencePlate' => [
                'required',
                'string',
                'min:4',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }
                    $plateKey = Vehicle::normalizePlateKey($value);
                    if (! preg_match('/^[A-Z0-9]{4,12}$/', $plateKey)) {
                        $fail('The licence plate must contain between 4 and 12 letters and digits.');
                    }
                },
            ],
            'make' => ['required', 'string', 'min:1', 'max:40'],
            'model' => ['required', 'string', 'min:1', 'max:60'],
            'year' => [
                'nullable',
                'integer',
                'min:1950',
                'max:'.((int) date('Y') + 1),
            ],
            'vin' => [
                'nullable',
                'string',
                'regex:/^[A-HJ-NPR-Za-hj-npr-z0-9]{17}$/',
            ],
        ];
    }

    /**
     * @return array{licencePlate: string, make: string, model: string, year: ?int, vin: ?string}
     */
    public function toPayload(): array
    {
        return [
            'licencePlate' => (string) $this->validated('licencePlate'),
            'make' => (string) $this->validated('make'),
            'model' => (string) $this->validated('model'),
            'year' => $this->validated('year') !== null ? (int) $this->validated('year') : null,
            'vin' => $this->validated('vin') !== null ? (string) $this->validated('vin') : null,
        ];
    }
}
