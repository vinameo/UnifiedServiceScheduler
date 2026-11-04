<?php

namespace App\Http\Requests;

use App\Domain\Auth\Role;
use App\Domain\Booking\CancelReason;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->user();
        $role = $user?->role instanceof Role ? $user->role : Role::tryFrom((string) $user?->role);
        $isCustomer = $role === Role::CUSTOMER;

        if ($isCustomer) {
            $allowedReasons = array_map(fn (CancelReason $r) => $r->value, CancelReason::forCustomer());

            return [
                'reason' => ['nullable', 'string', Rule::in($allowedReasons)],
                'note' => ['nullable', 'string', 'max:500'],
            ];
        }

        $allowedReasons = array_map(fn (CancelReason $r) => $r->value, CancelReason::forStaff());

        return [
            'reason' => ['required', 'string', Rule::in($allowedReasons)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
