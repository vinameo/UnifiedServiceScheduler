<?php

namespace App\Http\Requests;

use App\Support\Pagination\CursorPaginator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MyAppointmentsRequest extends FormRequest
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
            'scope' => ['required', 'string', 'in:upcoming,past'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
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
                        CursorPaginator::decode($rawCursor, ['start_at', 'id']);
                    } catch (\Throwable) {
                        $validator->errors()->add('cursor', 'The cursor is invalid.');
                    }
                }
            }
        });
    }

    public function scope(): string
    {
        return (string) ($this->query('scope') ?? $this->input('scope'));
    }

    public function scopeString(): string
    {
        return $this->scope();
    }

    public function limit(): int
    {
        return $this->has('limit') ? (int) ($this->query('limit') ?? $this->input('limit')) : 50;
    }

    public function cursor(): ?string
    {
        $val = $this->query('cursor') ?? $this->input('cursor');

        return ($val !== null && $val !== '') ? (string) $val : null;
    }
}
