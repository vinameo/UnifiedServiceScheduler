<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\Bay;
use App\Models\Dealership;
use App\Models\DealershipOpeningHour;
use App\Models\ServiceType;
use App\Models\Technician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private MasterDataRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(MasterDataRepositoryInterface::class);
    }

    public function test_service_types_returns_dealership_service_types_and_respects_active_filter(): void
    {
        $d1 = Dealership::factory()->create();
        $d2 = Dealership::factory()->create();

        $activeService = ServiceType::create([
            'dealership_id' => $d1->id,
            'code' => 'ACTIVE_SVC',
            'name' => 'Active Service',
            'duration_minutes' => 60,
            'required_level' => 1,
            'required_bay_type' => BayType::QUICK,
            'active' => true,
        ]);

        $inactiveService = ServiceType::create([
            'dealership_id' => $d1->id,
            'code' => 'INACTIVE_SVC',
            'name' => 'Inactive Service',
            'duration_minutes' => 30,
            'required_level' => 2,
            'required_bay_type' => BayType::STANDARD,
            'active' => false,
        ]);

        ServiceType::create([
            'dealership_id' => $d2->id,
            'code' => 'D2_SVC',
            'name' => 'Other Dealership Service',
            'duration_minutes' => 45,
            'required_level' => 3,
            'required_bay_type' => BayType::EV,
            'active' => true,
        ]);

        // All services for D1
        $all = $this->repository->serviceTypes($d1->id, activeOnly: false);
        $this->assertCount(2, $all);

        // Active only for D1
        $activeOnly = $this->repository->serviceTypes($d1->id, activeOnly: true);
        $this->assertCount(1, $activeOnly);
        $this->assertSame($activeService->id, $activeOnly[0]->id);
        $this->assertSame(BayType::QUICK, $activeOnly[0]->required_bay_type);
        $this->assertSame(1, $activeOnly[0]->requiredLevel()->value);

        // ServiceTypeFor
        $found = $this->repository->serviceTypeFor($d1->id, $activeService->id);
        $this->assertNotNull($found);
        $this->assertSame('ACTIVE_SVC', $found->code);

        // Foreign dealership returns null
        $this->assertNull($this->repository->serviceTypeFor($d2->id, $activeService->id));
        $this->assertNull($this->repository->serviceTypeFor($d1->id, '00000000-0000-0000-0000-000000000000'));
    }

    public function test_technicians_and_bays_and_opening_hours_read_scoped_to_dealership(): void
    {
        $dealership = Dealership::factory()->create();

        $tech = Technician::create([
            'dealership_id' => $dealership->id,
            'name' => 'Trần Minh An',
            'level' => 2,
            'active' => true,
        ]);

        $bay = Bay::create([
            'dealership_id' => $dealership->id,
            'code' => 'K1',
            'name' => 'Bay 1',
            'bay_type' => BayType::QUICK,
            'active' => true,
        ]);

        $openingHour = DealershipOpeningHour::create([
            'dealership_id' => $dealership->id,
            'weekday' => 1,
            'opens_at' => '08:00:00',
            'closes_at' => '17:00:00',
        ]);

        $technicians = $this->repository->technicians($dealership->id);
        $this->assertCount(1, $technicians);
        $this->assertSame($tech->id, $technicians[0]->id);
        $this->assertSame(2, $technicians[0]->level()->value);
        $this->assertTrue($technicians[0]->active);

        $bays = $this->repository->bays($dealership->id);
        $this->assertCount(1, $bays);
        $this->assertSame($bay->id, $bays[0]->id);
        $this->assertSame(BayType::QUICK, $bays[0]->bay_type);

        $hours = $this->repository->openingHours($dealership->id);
        $this->assertCount(1, $hours);
        $this->assertSame($openingHour->id, $hours[0]->id);
        $this->assertSame(1, $hours[0]->weekday);

        // Dealership relationships
        $this->assertCount(1, $dealership->technicians);
        $this->assertCount(1, $dealership->bays);
        $this->assertCount(1, $dealership->openingHours);

        // BelongsTo relationship
        $this->assertSame($dealership->id, $tech->dealership->id);
        $this->assertSame($dealership->id, $bay->dealership->id);
        $this->assertSame($dealership->id, $openingHour->dealership->id);
    }
}
