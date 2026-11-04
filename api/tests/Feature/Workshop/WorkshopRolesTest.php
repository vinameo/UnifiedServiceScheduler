<?php

namespace Tests\Feature\Workshop;

use App\Domain\Auth\Role;
use App\Domain\Booking\AppointmentStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppointmentFactory;
use Tests\Support\World;
use Tests\TestCase;

class WorkshopRolesTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-12-22: Given role, when workshop action invoked, assert permissions per README §4.
     * Service advisors can check-in and mark no-show (200), but cannot start, complete, or extend (403).
     * Foremen can perform all five actions (200).
     * Admins and customers cannot perform workshop actions (403).
     */
    #[DataProvider('workshopRolesProvider')]
    public function test_TC_12_22_workshop_action_role_permissions(
        Role $role,
        string $action,
        AppointmentStatus $initialStatus,
        int $expectedStatus,
    ): void {
        $world = World::saigonD7();

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $customerData = $world->customerWithVehicle();
        /** @var User $customerUser */
        $customerUser = $customerData['user'];
        $customer = $customerData['customer'];
        $vehicle = $customerData['vehicle'];

        $user = match ($role) {
            Role::SERVICE_ADVISOR => $world->advisor(),
            Role::WORKSHOP_FOREMAN => $world->foreman(),
            Role::ADMIN => $world->admin(),
            Role::CUSTOMER => $customerUser,
        };

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->subMinutes(30),
            end: $now->addMinutes(30),
            serviceType: $world->serviceType('OIL_CHANGE'),
            customer: $customer,
            vehicle: $vehicle,
            status: $initialStatus,
        );

        $payload = $action === 'extend' ? ['minutes' => 30] : [];

        $response = $this->actingAs($user)->postJson(
            "/api/v1/appointments/{$appointment->id}/{$action}",
            $payload,
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus($expectedStatus);

        if ($expectedStatus === 200) {
            $this->assertNotNull($response->headers->get('ETag'));
        }
    }

    /**
     * @return array<string, array{0: Role, 1: string, 2: AppointmentStatus, 3: int}>
     */
    public static function workshopRolesProvider(): array
    {
        return [
            // Service Advisor: check-in (200), no-show (200), start (403), complete (403), extend (403)
            'advisor check-in allowed' => [Role::SERVICE_ADVISOR, 'check-in', AppointmentStatus::CONFIRMED, 200],
            'advisor no-show allowed' => [Role::SERVICE_ADVISOR, 'no-show', AppointmentStatus::CONFIRMED, 200],
            'advisor start forbidden' => [Role::SERVICE_ADVISOR, 'start', AppointmentStatus::CHECKED_IN, 403],
            'advisor complete forbidden' => [Role::SERVICE_ADVISOR, 'complete', AppointmentStatus::IN_PROGRESS, 403],
            'advisor extend forbidden' => [Role::SERVICE_ADVISOR, 'extend', AppointmentStatus::IN_PROGRESS, 403],

            // Workshop Foreman: all allowed (200)
            'foreman check-in allowed' => [Role::WORKSHOP_FOREMAN, 'check-in', AppointmentStatus::CONFIRMED, 200],
            'foreman no-show allowed' => [Role::WORKSHOP_FOREMAN, 'no-show', AppointmentStatus::CONFIRMED, 200],
            'foreman start allowed' => [Role::WORKSHOP_FOREMAN, 'start', AppointmentStatus::CHECKED_IN, 200],
            'foreman complete allowed' => [Role::WORKSHOP_FOREMAN, 'complete', AppointmentStatus::IN_PROGRESS, 200],
            'foreman extend allowed' => [Role::WORKSHOP_FOREMAN, 'extend', AppointmentStatus::IN_PROGRESS, 200],

            // Admin: all forbidden (403)
            'admin check-in forbidden' => [Role::ADMIN, 'check-in', AppointmentStatus::CONFIRMED, 403],
            'admin no-show forbidden' => [Role::ADMIN, 'no-show', AppointmentStatus::CONFIRMED, 403],
            'admin start forbidden' => [Role::ADMIN, 'start', AppointmentStatus::CHECKED_IN, 403],
            'admin complete forbidden' => [Role::ADMIN, 'complete', AppointmentStatus::IN_PROGRESS, 403],
            'admin extend forbidden' => [Role::ADMIN, 'extend', AppointmentStatus::IN_PROGRESS, 403],

            // Customer: all forbidden (403)
            'customer check-in forbidden' => [Role::CUSTOMER, 'check-in', AppointmentStatus::CONFIRMED, 403],
            'customer no-show forbidden' => [Role::CUSTOMER, 'no-show', AppointmentStatus::CONFIRMED, 403],
            'customer start forbidden' => [Role::CUSTOMER, 'start', AppointmentStatus::CHECKED_IN, 403],
            'customer complete forbidden' => [Role::CUSTOMER, 'complete', AppointmentStatus::IN_PROGRESS, 403],
            'customer extend forbidden' => [Role::CUSTOMER, 'extend', AppointmentStatus::IN_PROGRESS, 403],
        ];
    }
}
