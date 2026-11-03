<?php

namespace Tests\Support;

use App\Models\Bay;
use App\Models\Customer;
use App\Models\Dealership;
use App\Models\ServiceType;
use App\Models\Technician;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\MasterDataSeeder;

class World
{
    public function __construct(public readonly Dealership $dealership) {}

    /**
     * Build the standard Saigon Auto D7 dealership with opening hours, 3 service types,
     * 4 bays, and 5 technicians with levels (Huỳnh Tấn Phát disabled).
     */
    public static function saigonD7(): self
    {
        $dealership = Dealership::firstOrCreate(
            ['code' => 'SGA-D7'],
            [
                'name' => 'Saigon Auto',
                'branch_name' => 'District 7',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'phone' => '028 3812 3456',
            ]
        );

        (new MasterDataSeeder)->seedForDealership($dealership);

        return new self($dealership);
    }

    /**
     * Seed standard master data for an arbitrary dealership.
     */
    public static function forDealership(Dealership $dealership): self
    {
        (new MasterDataSeeder)->seedForDealership($dealership);

        return new self($dealership);
    }

    public function serviceType(string $code): ServiceType
    {
        return ServiceType::where('dealership_id', $this->dealership->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    public function technician(string $name): Technician
    {
        return Technician::where('dealership_id', $this->dealership->id)
            ->where(function ($query) use ($name) {
                $query->where('name', $name)
                    ->orWhere('name', 'LIKE', "%{$name}%");
            })
            ->firstOrFail();
    }

    public function bay(string $code): Bay
    {
        return Bay::where('dealership_id', $this->dealership->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Deactivate all technicians except those matching the given names.
     *
     * @param  list<string>  $names
     */
    public function onlyTechnicians(array $names): static
    {
        $technicians = Technician::where('dealership_id', $this->dealership->id)->get();
        foreach ($technicians as $tech) {
            $matches = false;
            foreach ($names as $name) {
                if ($tech->name === $name || str_contains($tech->name, $name)) {
                    $matches = true;
                    break;
                }
            }
            $tech->update(['active' => $matches]);
        }

        return $this;
    }

    /**
     * Deactivate all bays except those with codes in the given list.
     *
     * @param  list<string>  $codes
     */
    public function onlyBays(array $codes): static
    {
        $bays = Bay::where('dealership_id', $this->dealership->id)->get();
        foreach ($bays as $bay) {
            $bay->update(['active' => in_array($bay->code, $codes, true)]);
        }

        return $this;
    }

    /**
     * Create a customer with an associated vehicle for testing.
     *
     * @param  array<string, mixed>  $vehicleAttributes
     * @return array{0: User, 1: Customer, 2: Vehicle, user: User, customer: Customer, vehicle: Vehicle}
     */
    public function customerWithVehicle(?User $user = null, array $vehicleAttributes = []): array
    {
        if ($user === null) {
            $user = User::factory()->customer()->create();
        }

        $customer = Customer::where('user_id', $user->id)->first();
        if (! $customer) {
            $customer = Customer::factory()->create([
                'user_id' => $user->id,
                'full_name' => $user->name,
            ]);
        }

        $vehicle = Vehicle::factory()->create(array_merge([
            'customer_id' => $customer->id,
        ], $vehicleAttributes));

        return [
            0 => $user,
            1 => $customer,
            2 => $vehicle,
            'user' => $user,
            'customer' => $customer,
            'vehicle' => $vehicle,
        ];
    }

    /**
     * Helper to create an admin user for this dealership.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function admin(?array $attributes = []): User
    {
        return User::factory()->admin($this->dealership)->create($attributes ?? []);
    }

    /**
     * Helper to create an advisor user for this dealership.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function advisor(?array $attributes = []): User
    {
        return User::factory()->advisor($this->dealership)->create($attributes ?? []);
    }

    /**
     * Helper to create a workshop foreman user for this dealership.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function foreman(?array $attributes = []): User
    {
        return User::factory()->foreman($this->dealership)->create($attributes ?? []);
    }
}
