<?php

namespace Database\Seeders;

use App\Domain\MasterData\BayType;
use App\Models\Bay;
use App\Models\Dealership;
use App\Models\DealershipOpeningHour;
use App\Models\ServiceType;
use App\Models\Technician;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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

        $this->seedForDealership($dealership);
    }

    /**
     * Seed master data for a given dealership. Safe to rerun with stable UUIDs (INV-01.6).
     */
    public function seedForDealership(Dealership $dealership): void
    {
        $this->seedOpeningHours($dealership);
        $this->seedServiceTypes($dealership);
        $this->seedBays($dealership);
        $this->seedTechnicians($dealership);
    }

    private function seedOpeningHours(Dealership $dealership): void
    {
        // 1-5: 08:00–17:00
        for ($weekday = 1; $weekday <= 5; $weekday++) {
            DealershipOpeningHour::updateOrCreate(
                [
                    'dealership_id' => $dealership->id,
                    'weekday' => $weekday,
                ],
                [
                    'opens_at' => '08:00:00',
                    'closes_at' => '17:00:00',
                ]
            );
        }

        // 6: 08:00–12:00
        DealershipOpeningHour::updateOrCreate(
            [
                'dealership_id' => $dealership->id,
                'weekday' => 6,
            ],
            [
                'opens_at' => '08:00:00',
                'closes_at' => '12:00:00',
            ]
        );

        // 7: no row (closed). If exists from prior write, remove it.
        DealershipOpeningHour::where('dealership_id', $dealership->id)
            ->where('weekday', 7)
            ->delete();
    }

    private function seedServiceTypes(Dealership $dealership): void
    {
        /** @var list<array{code: string, name: string, description: string, duration_minutes: int, required_level: int, required_bay_type: BayType, active: bool}> $serviceTypes */
        $serviceTypes = [
            [
                'code' => 'OIL_CHANGE',
                'name' => 'Oil change',
                'description' => 'oil and oil filter',
                'duration_minutes' => 60,
                'required_level' => 1,
                'required_bay_type' => BayType::QUICK,
                'active' => true,
            ],
            [
                'code' => 'MAINT_20K',
                'name' => '20,000 km service',
                'description' => 'full manufacturer checklist',
                'duration_minutes' => 120,
                'required_level' => 3,
                'required_bay_type' => BayType::STANDARD,
                'active' => true,
            ],
            [
                'code' => 'EV_BATTERY',
                'name' => 'EV battery check',
                'description' => 'by a level 5+ technician',
                'duration_minutes' => 90,
                'required_level' => 5,
                'required_bay_type' => BayType::EV,
                'active' => true,
            ],
        ];

        foreach ($serviceTypes as $serviceType) {
            ServiceType::updateOrCreate(
                [
                    'dealership_id' => $dealership->id,
                    'code' => $serviceType['code'],
                ],
                $serviceType
            );
        }
    }

    private function seedBays(Dealership $dealership): void
    {
        /** @var list<array{code: string, name: string, bay_type: BayType, active: bool}> $bays */
        $bays = [
            [
                'code' => 'K1',
                'name' => 'Bay 1',
                'bay_type' => BayType::QUICK,
                'active' => true,
            ],
            [
                'code' => 'K2',
                'name' => 'Bay 2',
                'bay_type' => BayType::QUICK,
                'active' => true,
            ],
            [
                'code' => 'K3',
                'name' => 'Bay 3',
                'bay_type' => BayType::STANDARD,
                'active' => true,
            ],
            [
                'code' => 'KEV',
                'name' => 'Bay EV',
                'bay_type' => BayType::EV,
                'active' => true,
            ],
        ];

        foreach ($bays as $bay) {
            Bay::updateOrCreate(
                [
                    'dealership_id' => $dealership->id,
                    'code' => $bay['code'],
                ],
                $bay
            );
        }
    }

    private function seedTechnicians(Dealership $dealership): void
    {
        /** @var list<array{name: string, level: int, active: bool}> $technicians */
        $technicians = [
            ['name' => 'Trần Minh An', 'level' => 2, 'active' => true],
            ['name' => 'Lý Hoàng Dũng', 'level' => 3, 'active' => true],
            ['name' => 'Võ Thị Chi', 'level' => 4, 'active' => true],
            ['name' => 'Ngô Bảo Em', 'level' => 6, 'active' => true],
            ['name' => 'Huỳnh Tấn Phát', 'level' => 1, 'active' => false],
        ];

        foreach ($technicians as $techData) {
            $technician = Technician::firstOrNew([
                'dealership_id' => $dealership->id,
                'name' => $techData['name'],
            ]);

            $technician->level = $techData['level'];

            // INV-01.6: The seeder sets level on every run but writes active only
            // when it creates the row, so a rerun never undoes an admin's disable or enable.
            if (! $technician->exists) {
                $technician->active = $techData['active'];
            }

            $technician->save();
        }
    }
}
