<?php

namespace App\Http\Requests;

use App\Support\Pagination\CursorPaginator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

class MonitoringErrorsRequest extends FormRequest
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
            'correlationId' => ['sometimes', 'nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._-]+$/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('cursor') && $this->input('cursor') !== null) {
                $rawCursor = (string) $this->input('cursor');
                if ($rawCursor === '') {
                    $validator->errors()->add('cursor', 'The cursor is invalid.');
                } else {
                    try {
                        CursorPaginator::decode($rawCursor, ['occurred_at', 'id']);
                    } catch (Throwable) {
                        $validator->errors()->add('cursor', 'The cursor is invalid.');
                    }
                }
            }
        });
    }

    public function correlationId(): ?string
    {
        $val = $this->query('correlationId');
        if (! is_string($val) || trim($val) === '') {
            return null;
        }

        return trim($val);
    }

    public function cursor(): ?string
    {
        return $this->filled('cursor') ? (string) $this->query('cursor') : null;
    }

    public function limit(): int
    {
        return $this->has('limit') ? (int) $this->query('limit') : 20;
    }
}
