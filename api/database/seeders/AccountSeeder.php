<?php

namespace Database\Seeders;

use App\Domain\Auth\Role;
use App\Models\Customer;
use App\Models\Dealership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dealership = Dealership::updateOrCreate(
            ['code' => 'SGA-D7'],
            [
                'name' => 'Saigon Auto',
                'branch_name' => 'District 7',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'phone' => '028 3812 3456',
            ]
        );

        $this->seedStaff(
            email: 'admin@saigonauto.test',
            name: 'Admin',
            role: Role::ADMIN,
            dealershipId: $dealership->id,
            configKey: 'auth.seed.admin_password',
        );

        $this->seedStaff(
            email: 'minhanh@saigonauto.test',
            name: 'Minh Anh',
            role: Role::SERVICE_ADVISOR,
            dealershipId: $dealership->id,
            configKey: 'auth.seed.advisor_password',
        );

        $this->seedStaff(
            email: 'quangtin@saigonauto.test',
            name: 'Quang Tín',
            role: Role::WORKSHOP_FOREMAN,
            dealershipId: $dealership->id,
            configKey: 'auth.seed.foreman_password',
        );

        $this->seedCustomer(
            email: 'hung@example.test',
            fullName: 'Nguyễn Văn Hùng',
            phone: '0903123218',
            configKey: 'auth.seed.customer_password',
        );

        $this->seedCustomer(
            email: 'khanh@example.test',
            fullName: 'Lâm Khánh',
            phone: '0912555019',
            configKey: 'auth.seed.customer_password',
        );
    }

    private function seedStaff(
        string $email,
        string $name,
        Role $role,
        string $dealershipId,
        string $configKey
    ): void {
        $password = config($configKey);
        if (! is_string($password) || $password === '') {
            $this->command?->warn("Skipping account {$email}: {$configKey} is empty or not set.");

            return;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $data = [
                'name' => $name,
                'role' => $role,
                'dealership_id' => $dealershipId,
            ];
            if (! Hash::check($password, $user->password)) {
                $data['password'] = Hash::make($password);
            }
            $user->update($data);
        } else {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
                'dealership_id' => $dealershipId,
                'email_verified_at' => now(),
            ]);
        }
    }

    private function seedCustomer(
        string $email,
        string $fullName,
        string $phone,
        string $configKey
    ): void {
        $password = config($configKey);
        if (! is_string($password) || $password === '') {
            $this->command?->warn("Skipping account {$email}: {$configKey} is empty or not set.");

            return;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $data = [
                'name' => $fullName,
                'role' => Role::CUSTOMER,
                'dealership_id' => null,
            ];
            if (! Hash::check($password, $user->password)) {
                $data['password'] = Hash::make($password);
            }
            $user->update($data);
        } else {
            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => Role::CUSTOMER,
                'dealership_id' => null,
                'email_verified_at' => now(),
            ]);
        }

        Customer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $fullName,
                'phone' => $phone,
            ]
        );
    }
}
