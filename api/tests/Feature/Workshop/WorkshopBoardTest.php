<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class WorkshopBoardTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-12-16 (AC-12.12; Integration): given Today: blocks in K1, K3; X IN_PROGRESS ended 20 min ago;
     * a cancelled booking; when GET workshop-board?date=today;
     * then Bays with blocks (cancelled hidden); X overrunMinutes 20;
     * alert OVERRUN for X with nextBooking;
     * technicians: active only, with level, bookedMinutes and status (OVERRUN for X's technician).
     */
    public function test_AC_12_12_board(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        // Clock frozen at 10:50 +07
        $now = CarbonImmutable::parse('2026-11-02 10:50:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $techAn = $world->technician('An');
        $techChi = $world->technician('Chi');
        $bayK1 = $world->bay('K1');
        $bayK3 = $world->bay('K3');

        $customer1 = $world->customerWithVehicle();
        $customer2 = $world->customerWithVehicle();
        $customer3 = $world->customerWithVehicle();

        // Block in K1: 08:30 - 10:30 (120 min), technician An, CONFIRMED
        $aptK1 = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techAn,
            bay: $bayK1,
            start: CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customer1['customer'],
            vehicle: $customer1['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        // Block in K3: X IN_PROGRESS, 09:00 - 10:30 (ended 20 min ago at 10:50, 90 min duration), technician Chi
        $aptX = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techChi,
            bay: $bayK3,
            start: CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('MAINT_20K'),
            customer: $customer2['customer'],
            vehicle: $customer2['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );
        $aptX->update([
            'version' => $aptX->version + 1,
            'started_at' => CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh')->utc(),
        ]);

        // Cancelled booking in K3 following X: 14:00 - 15:00, technician Chi, CANCELLED
        $aptCancelled = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techChi,
            bay: $bayK3,
            start: CarbonImmutable::parse('2026-11-02 14:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 15:00:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customer3['customer'],
            vehicle: $customer3['vehicle'],
            status: AppointmentStatus::CANCELLED,
        );
        $aptCancelled->update([
            'version' => $aptCancelled->version + 1,
            'cancelled_at' => CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh')->utc(),
        ]);
        // Cancelled appointments have inactive allocations
        app(ResourceAllocationRepositoryInterface::class)
            ->deactivateFor($aptCancelled->id, $now);

        // Call GET /api/v1/dealerships/{d}/workshop-board?date=today
        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=today"
        );

        $response->assertStatus(200);

        /** @var array{date: string, now: string, opensAt: string, closesAt: string, bays: list<array{id: string, code: string, name: string, bayType: string, blocks: list<array<string, mixed>>}>, displaced: list<array<string, mixed>>, technicians: list<array{id: string, name: string, level: int, bookedMinutes: int, status: string}>, alerts: list<array<string, mixed>>} $data */
        $data = $response->json('data');
        $this->assertSame('2026-11-02', $data['date']);
        $this->assertSame('2026-11-02T10:50:00+07:00', $data['now']);
        $this->assertSame('08:00', $data['opensAt']);
        $this->assertSame('17:00', $data['closesAt']);

        // 1. Bays with blocks: cancelled hidden
        $bays = collect($data['bays'])->keyBy('code');
        $this->assertTrue($bays->has('K1'));
        $this->assertTrue($bays->has('K3'));

        $bayK1Item = $bays->get('K1');
        $this->assertNotNull($bayK1Item);
        $k1Blocks = $bayK1Item['blocks'];
        $this->assertCount(1, $k1Blocks);
        $this->assertSame($aptK1->id, $k1Blocks[0]['appointmentId']);
        $this->assertSame(0, $k1Blocks[0]['overrunMinutes']);

        $bayK3Item = $bays->get('K3');
        $this->assertNotNull($bayK3Item);
        $k3Blocks = $bayK3Item['blocks'];
        $this->assertCount(1, $k3Blocks);
        $this->assertSame($aptX->id, $k3Blocks[0]['appointmentId']);
        $this->assertSame('IN_PROGRESS', $k3Blocks[0]['status']);
        $this->assertSame(20, $k3Blocks[0]['overrunMinutes']);

        // 2. Alert OVERRUN for X with nextBooking
        $alerts = $data['alerts'];
        $this->assertCount(1, $alerts);
        $this->assertSame('OVERRUN', $alerts[0]['type']);
        $this->assertSame($aptX->id, $alerts[0]['appointmentId']);
        $this->assertSame($aptX->version, $alerts[0]['version']);
        $this->assertSame('Bay 3', $alerts[0]['bayName']);
        $this->assertSame(20, $alerts[0]['minutesOver']);

        $nextBooking = $alerts[0]['nextBooking'];
        $this->assertNotNull($nextBooking);
        $this->assertSame($aptCancelled->id, $nextBooking['appointmentId']);
        $this->assertSame('2026-11-02T14:00:00+07:00', $nextBooking['start']);
        $this->assertSame('CANCELLED', $nextBooking['status']);

        // 3. Technicians: active only, with level, bookedMinutes, status
        $technicians = collect($data['technicians'])->keyBy('name');

        // Disabled technician (Huỳnh Tấn Phát) MUST NOT be listed
        $this->assertFalse($technicians->has('Huỳnh Tấn Phát'));

        // Chi (technician for X) has status OVERRUN and 90 booked minutes (cancelled excluded)
        $this->assertTrue($technicians->has('Võ Thị Chi'));
        $chi = $technicians->get('Võ Thị Chi');
        $this->assertNotNull($chi);
        $this->assertSame($techChi->level, $chi['level']);
        $this->assertSame(90, $chi['bookedMinutes']);
        $this->assertSame('OVERRUN', $chi['status']);

        // An has status WORKING and 120 booked minutes
        $this->assertTrue($technicians->has('Trần Minh An'));
        $an = $technicians->get('Trần Minh An');
        $this->assertNotNull($an);
        $this->assertSame($techAn->level, $an['level']);
        $this->assertSame(120, $an['bookedMinutes']);
        $this->assertSame('WORKING', $an['status']);

        // Active technicians without bookings have 0 booked minutes and WORKING
        $this->assertTrue($technicians->has('Lý Hoàng Dũng'));
        $dung = $technicians->get('Lý Hoàng Dũng');
        $this->assertNotNull($dung);
        $this->assertSame(0, $dung['bookedMinutes']);
        $this->assertSame('WORKING', $dung['status']);

        // Displaced bookings array is empty
        $this->assertSame([], $data['displaced']);

        // Querying with explicit date string also works identically
        $responseDate = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-11-02"
        );
        $responseDate->assertStatus(200);
        $this->assertSame($data, $responseDate->json('data'));
    }

    public function test_board_with_displaced_booking(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $customer = $world->customerWithVehicle();
        $bay = $world->bay('K1');
        $tech = $world->technician('An');

        // Displaced booking on this day
        $displacedApt = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 12:00:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customer['customer'],
            vehicle: $customer['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );
        $displacedApt->update([
            'version' => $displacedApt->version + 1,
            'contact_required_reason' => 'OVERRUN',
        ]);
        app(ResourceAllocationRepositoryInterface::class)
            ->deactivateFor($displacedApt->id, $now);

        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-11-02"
        );

        $response->assertStatus(200);
        /** @var array{date: string, now: string, opensAt: string, closesAt: string, bays: list<array{id: string, code: string, name: string, bayType: string, blocks: list<array<string, mixed>>}>, displaced: list<array{appointmentId: string, reference: string, start: string, end: string, reason: string}>, technicians: list<array{id: string, name: string, level: int, bookedMinutes: int, status: string}>, alerts: list<array<string, mixed>>} $data */
        $data = $response->json('data');

        // Displaced booking is present in displaced array
        $this->assertCount(1, $data['displaced']);
        $this->assertSame($displacedApt->id, $data['displaced'][0]['appointmentId']);
        $this->assertSame('OVERRUN', $data['displaced'][0]['reason']);

        // And NOT present in K1 blocks
        $k1 = collect($data['bays'])->firstWhere('code', 'K1');
        $this->assertNotNull($k1);
        $this->assertSame([], $k1['blocks']);

        // And NOT counted in technician booked minutes
        $an = collect($data['technicians'])->firstWhere('name', 'Trần Minh An');
        $this->assertNotNull($an);
        $this->assertSame(0, $an['bookedMinutes']);
    }

    public function test_board_filters_out_expired_holds(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $customer = $world->customerWithVehicle();
        $bay = $world->bay('K1');
        $tech = $world->technician('An');

        // Expired hold (expired at 09:55)
        $expiredHold = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 12:00:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customer['customer'],
            vehicle: $customer['vehicle'],
            status: AppointmentStatus::HELD,
            expiresAt: CarbonImmutable::parse('2026-11-02 09:55:00', 'Asia/Ho_Chi_Minh'),
        );

        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-11-02"
        );

        $response->assertStatus(200);
        /** @var array{date: string, now: string, opensAt: string, closesAt: string, bays: list<array{id: string, code: string, name: string, bayType: string, blocks: list<array<string, mixed>>}>, displaced: list<array<string, mixed>>, technicians: list<array<string, mixed>>, alerts: list<array<string, mixed>>} $data */
        $data = $response->json('data');

        $k1 = collect($data['bays'])->firstWhere('code', 'K1');
        $this->assertNotNull($k1);
        $this->assertSame([], $k1['blocks']);
    }

    /**
     * D-12.13: an EXPIRED hold is hidden and does not count towards the technician's booked minutes.
     */
    public function test_D_12_13_board_hides_expired_appointments(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $customer = $world->customerWithVehicle();
        $expired = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K2'),
            start: CarbonImmutable::parse('2026-11-02 14:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 15:00:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customer['customer'],
            vehicle: $customer['vehicle'],
            status: AppointmentStatus::EXPIRED,
            expiresAt: CarbonImmutable::parse('2026-11-02 09:55:00', 'Asia/Ho_Chi_Minh'),
        );
        app(ResourceAllocationRepositoryInterface::class)->deactivateFor($expired->id, $now);

        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-11-02"
        );

        $response->assertStatus(200);
        /** @var array{bays: list<array{code: string, blocks: list<array<string, mixed>>}>, technicians: list<array{name: string, bookedMinutes: int}>} $data */
        $data = $response->json('data');

        $k2 = collect($data['bays'])->firstWhere('code', 'K2');
        $this->assertNotNull($k2);
        $this->assertSame([], $k2['blocks']);

        $an = collect($data['technicians'])->firstWhere('name', $world->technician('An')->name);
        $this->assertNotNull($an);
        $this->assertSame(0, $an['bookedMinutes']);
    }

    /**
     * D-12.4: nextBooking names the booking an extend would touch. A cancelled booking that comes
     * earlier does not hide a live one on the same technician.
     */
    public function test_next_booking_skips_cancelled_when_a_live_booking_follows(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $now = CarbonImmutable::parse('2026-11-02 10:50:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $techChi = $world->technician('Chi');
        $customerX = $world->customerWithVehicle();
        $customerC = $world->customerWithVehicle();
        $customerB = $world->customerWithVehicle();

        AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techChi,
            bay: $world->bay('K3'),
            start: CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 10:30:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('MAINT_20K'),
            customer: $customerX['customer'],
            vehicle: $customerX['vehicle'],
            status: AppointmentStatus::IN_PROGRESS,
        );

        // Cancelled booking in X's bay at 11:00
        $cancelled = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('Dũng'),
            bay: $world->bay('K3'),
            start: CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 12:00:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customerC['customer'],
            vehicle: $customerC['vehicle'],
            status: AppointmentStatus::CANCELLED,
        );
        app(ResourceAllocationRepositoryInterface::class)->deactivateFor($cancelled->id, $now);

        // Live booking for X's technician in another bay at 11:30
        $live = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $techChi,
            bay: $world->bay('K1'),
            start: CarbonImmutable::parse('2026-11-02 11:30:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 12:30:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customerB['customer'],
            vehicle: $customerB['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-11-02"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.alerts.0.nextBooking.appointmentId', $live->id);
        $response->assertJsonPath('data.alerts.0.nextBooking.status', 'CONFIRMED');
        $response->assertJsonPath('data.alerts.0.nextBooking.start', '2026-11-02T11:30:00+07:00');
    }

    public function test_board_adversarial_invalid_date(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        // API §B4 Row: date=2026-02-30 -> 400 VALIDATION_ERROR
        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-02-30"
        );

        $response->assertStatus(400);
        $response->assertJsonPath('code', 'VALIDATION_ERROR');

        // Non-date string
        $responseInvalid = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=invalid-date"
        );

        $responseInvalid->assertStatus(400);
        $responseInvalid->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_board_forbidden_for_customers(): void
    {
        $world = World::saigonD7();
        $customerData = $world->customerWithVehicle();

        $response = $this->actingAs($customerData['user'])->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board"
        );

        $response->assertStatus(403);
    }
}
