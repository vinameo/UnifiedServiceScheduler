<?php

namespace App\Http\Requests\Auth;

use App\Domain\Auth\Exceptions\WeakPassword;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CustomerSignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $email = $this->input('email');
        if (is_string($email)) {
            $merge['email'] = strtolower(trim($email));
        }

        $fullName = $this->input('fullName');
        if (is_string($fullName)) {
            $merge['fullName'] = trim($fullName);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'max:254',
                'regex:/^[\x21-\x7E]+$/',
                'email:rfc',
            ],
            'password' => [
                'required',
                'string',
                'min:1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The password must not be greater than 72 bytes.');
                    }
                },
            ],
            'passwordConfirmation' => [
                'required',
                'same:password',
            ],
            'fullName' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'phone' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! preg_match('/^\+?[0-9\s\-.()]+$/', $value)) {
                        $fail('Enter a valid phone number.');

                        return;
                    }

                    $digits = preg_replace('/\D/', '', $value);
                    if ($digits === null || strlen($digits) < 9 || strlen($digits) > 15) {
                        $fail('Enter a valid phone number.');

                        return;
                    }
                },
            ],
        ];
    }

    protected function passedValidation(): void
    {
        $password = (string) $this->input('password');

        if (mb_strlen($password) < 8 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            throw new WeakPassword;
        }
    }
}
