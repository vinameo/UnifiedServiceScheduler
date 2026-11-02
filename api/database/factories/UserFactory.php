<?php

namespace Database\Factories;

use App\Domain\Auth\Role;
use App\Models\Dealership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::CUSTOMER,
        ]);
    }

    public function advisor(Dealership|string|null $dealership = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::SERVICE_ADVISOR,
            'dealership_id' => $dealership instanceof Dealership ? $dealership->id : ($dealership ?? (string) Str::uuid()),
        ]);
    }

    public function foreman(Dealership|string|null $dealership = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::WORKSHOP_FOREMAN,
            'dealership_id' => $dealership instanceof Dealership ? $dealership->id : ($dealership ?? (string) Str::uuid()),
        ]);
    }

    public function admin(Dealership|string|null $dealership = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::ADMIN,
            'dealership_id' => $dealership instanceof Dealership ? $dealership->id : ($dealership ?? (string) Str::uuid()),
        ]);
    }
}
