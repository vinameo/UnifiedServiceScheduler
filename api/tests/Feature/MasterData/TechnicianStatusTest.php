<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\MasterData\Services\UpcomingAssignmentsReader;
use App\Models\Dealership;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\World;
use Tests\TestCase;

class TechnicianStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-01-16 (AC-01.7, INV-01.7):
     * Given World; Chi has no appointment;
     * When Admin PUT /dealerships/{d}/technicians/{Chi}/status {active: false};
     * Then 200 active: false; AllocationSnapshotRepository / MasterDataRepository snapshot lists Chi inactive.
     */
    public function test_AC_01_7_disable_without_bookings(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $admin = $world->admin();
        $chi = $world->technician('Võ Thị Chi');

        $this->assertTrue($chi->active);

        // Chi has no appointment (default NoUpcomingAssignments reports 0 bookings)
        $response = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$chi->id}/status", [
                'active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $chi->id)
            ->assertJsonPath('data.name', 'Võ Thị Chi')
            ->assertJsonPath('data.active', false);

        $freshChi = $chi->fresh();
        $this->assertNotNull($freshChi);
        $this->assertFalse($freshChi->active);

        // Snapshot in MasterDataRepository lists Chi as inactive
        $masterDataRepo = app(MasterDataRepositoryInterface::class);
        $technicians = $masterDataRepo->technicians($dealership->id);
        $chiInRepo = collect($technicians)->firstWhere('id', $chi->id);
        $this->assertNotNull($chiInRepo);
        $this->assertFalse($chiInRepo->active);
    }

    /**
     * TC-01-20 (AC-01.10, INV-01.8, D-01.12, API §B4):
     * Given Admin of D1; D2's technician; advisor; a disabled technician;
     * When Each row of API §B4 for API-01.6; admin enables the disabled one twice;
     * Then Status and code as in the table; enable: 200 both times, active: true.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('adversarialInputProvider')]
    public function test_API_01_6_adversarial_input(
        array $payload,
        string $callerRole,
        string $technicianType,
        int $expectedStatus,
        ?string $expectedCode,
        ?bool $assertDbActive = null,
    ): void {
        $world1 = World::saigonD7();
        $dealership1 = $world1->dealership;

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
        ]);
        $world2 = World::forDealership($dealership2);

        // Users
        $admin = $world1->admin();
        $advisor = $world1->advisor();
        $foreman = $world1->foreman();
        $customer = $world1->customerWithVehicle()['user'];

        /** @var User $caller */
        $caller = match ($callerRole) {
            'admin' => $admin,
            'advisor' => $advisor,
            'foreman' => $foreman,
            'customer' => $customer,
            default => throw new \InvalidArgumentException("Unknown caller role: {$callerRole}"),
        };

        // Technicians
        $activeTech = $world1->technician('Võ Thị Chi');
        $disabledTech = $world1->technician('Huỳnh Tấn Phát');
        $d2Tech = $world2->technician('Võ Thị Chi');

        $technicianId = match ($technicianType) {
            'active' => $activeTech->id,
            'disabled' => $disabledTech->id,
            'd2_tech' => $d2Tech->id,
            'abc' => 'abc',
            'missing_uuid' => (string) Str::uuid(),
            'enable_twice' => $disabledTech->id,
            default => throw new \InvalidArgumentException("Unknown technician type: {$technicianType}"),
        };

        if ($technicianType === 'enable_twice') {
            // First enable
            $response1 = $this->actingAs($caller)
                ->putJson("/api/v1/dealerships/{$dealership1->id}/technicians/{$technicianId}/status", $payload);

            $response1->assertStatus(200)
                ->assertJsonPath('data.active', true);

            // Second enable (idempotent no-op)
            $response2 = $this->actingAs($caller)
                ->putJson("/api/v1/dealerships/{$dealership1->id}/technicians/{$technicianId}/status", $payload);

            $response2->assertStatus(200)
                ->assertJsonPath('data.active', true);

            $freshDisabled = $disabledTech->fresh();
            $this->assertNotNull($freshDisabled);
            $this->assertTrue($freshDisabled->active);

            return;
        }

        $response = $this->actingAs($caller)
            ->putJson("/api/v1/dealerships/{$dealership1->id}/technicians/{$technicianId}/status", $payload);

        $response->assertStatus($expectedStatus);

        if ($expectedCode !== null) {
            $response->assertJsonPath('code', $expectedCode);
        }

        if ($expectedStatus === 200) {
            $expectedActive = (bool) $payload['active'];
            $response->assertJsonPath('data.active', $expectedActive);

            if ($assertDbActive !== null) {
                /** @var Technician $targetTech */
                $targetTech = Technician::findOrFail($technicianId);
                $this->assertSame($assertDbActive, $targetTech->active);

                // If extra fields were provided (e.g. level, name), verify they were ignored
                if (isset($payload['level'])) {
                    $this->assertNotSame($payload['level'], $targetTech->level);
                }
                if (isset($payload['name'])) {
                    $this->assertNotSame($payload['name'], $targetTech->name);
                }
            }
        }
    }

    /**
     * @return array<string, array{
     *     payload: array<string, mixed>,
     *     callerRole: string,
     *     technicianType: string,
     *     expectedStatus: int,
     *     expectedCode: string|null,
     *     assertDbActive?: bool|null,
     * }>
     */
    public static function adversarialInputProvider(): array
    {
        return [
            'active_missing' => [
                'payload' => [],
                'callerRole' => 'admin',
                'technicianType' => 'active',
                'expectedStatus' => 400,
                'expectedCode' => 'VALIDATION_ERROR',
            ],
            'active_string_false' => [
                'payload' => ['active' => 'false'],
                'callerRole' => 'admin',
                'technicianType' => 'active',
                'expectedStatus' => 400,
                'expectedCode' => 'VALIDATION_ERROR',
            ],
            'active_integer_zero' => [
                'payload' => ['active' => 0],
                'callerRole' => 'admin',
                'technicianType' => 'active',
                'expectedStatus' => 400,
                'expectedCode' => 'VALIDATION_ERROR',
            ],
            'active_null' => [
                'payload' => ['active' => null],
                'callerRole' => 'admin',
                'technicianType' => 'active',
                'expectedStatus' => 400,
                'expectedCode' => 'VALIDATION_ERROR',
            ],
            'technician_abc' => [
                'payload' => ['active' => false],
                'callerRole' => 'admin',
                'technicianType' => 'abc',
                'expectedStatus' => 404,
                'expectedCode' => 'TECHNICIAN_NOT_FOUND',
            ],
            'technician_missing_uuid' => [
                'payload' => ['active' => false],
                'callerRole' => 'admin',
                'technicianType' => 'missing_uuid',
                'expectedStatus' => 404,
                'expectedCode' => 'TECHNICIAN_NOT_FOUND',
            ],
            'technician_foreign_dealership' => [
                'payload' => ['active' => false],
                'callerRole' => 'admin',
                'technicianType' => 'd2_tech',
                'expectedStatus' => 404,
                'expectedCode' => 'TECHNICIAN_NOT_FOUND',
            ],
            'caller_advisor' => [
                'payload' => ['active' => false],
                'callerRole' => 'advisor',
                'technicianType' => 'active',
                'expectedStatus' => 403,
                'expectedCode' => 'FORBIDDEN',
            ],
            'caller_foreman' => [
                'payload' => ['active' => false],
                'callerRole' => 'foreman',
                'technicianType' => 'active',
                'expectedStatus' => 403,
                'expectedCode' => 'FORBIDDEN',
            ],
            'caller_customer' => [
                'payload' => ['active' => false],
                'callerRole' => 'customer',
                'technicianType' => 'active',
                'expectedStatus' => 403,
                'expectedCode' => 'FORBIDDEN',
            ],
            'disable_already_disabled' => [
                'payload' => ['active' => false],
                'callerRole' => 'admin',
                'technicianType' => 'disabled',
                'expectedStatus' => 200,
                'expectedCode' => null,
                'assertDbActive' => false,
            ],
            'enable_already_active' => [
                'payload' => ['active' => true],
                'callerRole' => 'admin',
                'technicianType' => 'active',
                'expectedStatus' => 200,
                'expectedCode' => null,
                'assertDbActive' => true,
            ],
            'payload_with_extra_fields' => [
                'payload' => ['active' => false, 'level' => 7, 'name' => 'Should Not Change'],
                'callerRole' => 'admin',
                'technicianType' => 'active',
                'expectedStatus' => 200,
                'expectedCode' => null,
                'assertDbActive' => false,
            ],
            'enable_disabled_twice' => [
                'payload' => ['active' => true],
                'callerRole' => 'admin',
                'technicianType' => 'enable_twice',
                'expectedStatus' => 200,
                'expectedCode' => null,
                'assertDbActive' => true,
            ],
        ];
    }

    /**
     * Prove that abc, missing UUID, and foreign technician return identical bodies except correlationId.
     */
    public function test_INV_01_8_missing_malformed_and_foreign_technician_bodies_are_identical(): void
    {
        $world1 = World::saigonD7();
        $dealership1 = $world1->dealership;
        $admin = $world1->admin();

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
        ]);
        $world2 = World::forDealership($dealership2);
        $d2Tech = $world2->technician('Võ Thị Chi');

        $payload = ['active' => false];

        $respAbc = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership1->id}/technicians/abc/status", $payload);
        $respMissing = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership1->id}/technicians/".Str::uuid().'/status', $payload);
        $respForeign = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership1->id}/technicians/{$d2Tech->id}/status", $payload);

        $respAbc->assertStatus(404)->assertJsonPath('code', 'TECHNICIAN_NOT_FOUND');
        $respMissing->assertStatus(404)->assertJsonPath('code', 'TECHNICIAN_NOT_FOUND');
        $respForeign->assertStatus(404)->assertJsonPath('code', 'TECHNICIAN_NOT_FOUND');

        $bodyAbc = $respAbc->json();
        $bodyMissing = $respMissing->json();
        $bodyForeign = $respForeign->json();

        unset($bodyAbc['correlationId'], $bodyMissing['correlationId'], $bodyForeign['correlationId']);

        $this->assertSame($bodyAbc, $bodyMissing);
        $this->assertSame($bodyAbc, $bodyForeign);
    }

    /**
     * Prove that if upcoming assignments exist, disabling is refused with 409 TECHNICIAN_HAS_BOOKINGS.
     */
    public function test_disable_refused_with_409_when_technician_has_upcoming_bookings(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $admin = $world->admin();
        $chi = $world->technician('Võ Thị Chi');

        // Bind mock reader reporting 2 upcoming appointments
        $fakeReader = new class implements UpcomingAssignmentsReader
        {
            public function count(string $technicianId, CarbonImmutable $now): int
            {
                return 2;
            }

            public function preview(string $technicianId, CarbonImmutable $now, int $limit = 20): array
            {
                return [
                    [
                        'id' => '01930000-0000-7000-8000-000000000001',
                        'reference' => 'APT-20261104-0001',
                        'start' => '2026-11-04T09:00:00+07:00',
                        'end' => '2026-11-04T10:00:00+07:00',
                        'customerName' => 'Nguyễn Văn A',
                    ],
                    [
                        'id' => '01930000-0000-7000-8000-000000000002',
                        'reference' => 'APT-20261104-0002',
                        'start' => '2026-11-04T14:00:00+07:00',
                        'end' => '2026-11-04T15:00:00+07:00',
                        'customerName' => 'Trần Thị B',
                    ],
                ];
            }
        };

        $this->app->instance(UpcomingAssignmentsReader::class, $fakeReader);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$chi->id}/status", [
                'active' => false,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'TECHNICIAN_HAS_BOOKINGS')
            ->assertJsonPath('count', 2)
            ->assertJsonPath('appointments.0.reference', 'APT-20261104-0001')
            ->assertJsonPath('appointments.1.customerName', 'Trần Thị B');

        // Chi remains active
        $freshChi = $chi->fresh();
        $this->assertNotNull($freshChi);
        $this->assertTrue($freshChi->active);
    }
}
