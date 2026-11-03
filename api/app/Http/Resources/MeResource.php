<?php

namespace App\Http\Resources;

use App\Domain\Auth\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dealership = $this->dealership;
        $customer = $this->customer;
        $role = $this->role instanceof Role ? $this->role->value : (string) $this->role;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $role,
            'dealership' => $dealership ? [
                'id' => $dealership->id,
                'code' => $dealership->code,
                'name' => $dealership->name,
                'branchName' => $dealership->branch_name,
                'timezone' => $dealership->timezone,
                'phone' => $dealership->phone,
            ] : null,
            'customer' => $customer ? [
                'id' => $customer->id,
                'fullName' => $customer->full_name,
                'phone' => $customer->phone,
            ] : null,
        ];
    }
}
