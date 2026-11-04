<?php

namespace App\Http\Requests;

use App\Support\Pagination\CursorPaginator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

class ListOutboxMessagesRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', 'in:PENDING,RETRYING,FAILED,SENT,SKIPPED'],
            'channel' => ['sometimes', 'nullable', 'string', 'in:SMS,EMAIL,DMS'],
            'eventType' => ['sometimes', 'nullable', 'string', 'in:AppointmentConfirmed,AppointmentCancelled,AppointmentRescheduled,AppointmentReminder,AppointmentReassigned'],
            'appointmentId' => ['sometimes', 'nullable', 'uuid'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('cursor') && $this->input('cursor') !== null) {
                $rawCursor = (string) $this->input('cursor');
                if ($rawCursor !== '') {
                    try {
                        CursorPaginator::decode($rawCursor, ['created_at', 'id']);
                    } catch (Throwable) {
                        $validator->errors()->add('cursor', 'The cursor is invalid.');
                    }
                }
            }
        });
    }

    public function status(): ?string
    {
        return $this->filled('status') ? (string) $this->query('status') : null;
    }

    public function channel(): ?string
    {
        return $this->filled('channel') ? (string) $this->query('channel') : null;
    }

    public function eventType(): ?string
    {
        return $this->filled('eventType') ? (string) $this->query('eventType') : null;
    }

    public function appointmentId(): ?string
    {
        return $this->filled('appointmentId') ? (string) $this->query('appointmentId') : null;
    }

    public function limit(): int
    {
        return $this->filled('limit') ? (int) $this->query('limit') : 50;
    }

    public function cursor(): ?string
    {
        return $this->filled('cursor') ? (string) $this->query('cursor') : null;
    }
}
