<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MonitoringSummaryRequest extends FormRequest
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
            'range' => ['required', 'string', 'in:1h,24h,7d'],
        ];
    }

    public function range(): string
    {
        return (string) $this->query('range');
    }
}
