<?php

namespace App\Http\Requests;

use App\Domain\Booking\AppointmentStatus;
use App\Support\Pagination\CursorPaginator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DealershipAppointmentsRequest extends FormRequest
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
            'date' => ['required_unless:contactRequired,true,1', 'nullable', 'date_format:Y-m-d'],
            'contactRequired' => ['sometimes', 'in:true,false,1,0'],
            'technicianId' => ['sometimes', 'uuid'],
            'bayId' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('status') && $this->input('status') !== null) {
                $rawStatus = (string) $this->input('status');
                if ($rawStatus === '') {
                    $validator->errors()->add('status', 'The status is invalid.');
                } else {
                    $statuses = explode(',', $rawStatus);
                    $validStatuses = array_column(AppointmentStatus::cases(), 'value');
                    foreach ($statuses as $st) {
                        if ($st === '' || ! in_array($st, $validStatuses, true)) {
                            $validator->errors()->add('status', 'The status is invalid.');
                            break;
                        }
                    }
                }
            }

            if ($this->has('cursor') && $this->input('cursor') !== null) {
                $rawCursor = (string) $this->input('cursor');
                if ($rawCursor === '') {
                    $validator->errors()->add('cursor', 'The cursor is invalid.');
                } else {
                    try {
                        CursorPaginator::decode($rawCursor, ['start_at', 'id']);
                    } catch (\Throwable) {
                        $validator->errors()->add('cursor', 'The cursor is invalid.');
                    }
                }
            }
        });
    }

    public function dateString(): ?string
    {
        return $this->filled('date') ? (string) $this->query('date') : null;
    }

    public function contactRequired(): bool
    {
        return $this->boolean('contactRequired');
    }

    public function technicianId(): ?string
    {
        return $this->filled('technicianId') ? (string) $this->query('technicianId') : null;
    }

    public function bayId(): ?string
    {
        return $this->filled('bayId') ? (string) $this->query('bayId') : null;
    }

    /**
     * @return list<string>|null
     */
    public function statuses(): ?array
    {
        if (! $this->has('status') || $this->query('status') === null) {
            return null;
        }

        $raw = (string) $this->query('status');
        if ($raw === '') {
            return null;
        }

        return explode(',', $raw);
    }

    public function limit(): int
    {
        return $this->has('limit') ? (int) $this->query('limit') : 50;
    }

    public function cursor(): ?string
    {
        return $this->filled('cursor') ? (string) $this->query('cursor') : null;
    }
}
