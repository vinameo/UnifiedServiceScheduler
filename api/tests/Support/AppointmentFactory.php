<?php

namespace Tests\Support;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\ResourceType;
use App\Models\Appointment;
use App\Models\Bay;
use App\Models\Customer;
use App\Models\Dealership;
use App\Models\ServiceType;
use App\Models\Technician;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentFactory
{
    public static function create(
        Dealership $dealership,
        Technician $technician,
        Bay $bay,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?ServiceType $serviceType = null,
        ?Customer $customer = null,
        ?Vehicle $vehicle = null,
        AppointmentStatus $status = AppointmentStatus::CONFIRMED,
    ): Appointment {
        $now = CarbonImmutable::now();
        $tz = $dealership->timezone;
        $startInLocalTz = $start->setTimezone($tz);

        if ($customer === null) {
            $user = User::factory()->customer()->create();
            $customer = Customer::factory()->create([
                'user_id' => $user->id,
                'full_name' => 'Test Customer',
            ]);
        }

        if ($vehicle === null) {
            $vehicle = Vehicle::factory()->create([
                'customer_id' => $customer->id,
            ]);
        }

        if ($serviceType === null) {
            $serviceType = ServiceType::where('dealership_id', $dealership->id)->firstOrFail();
        }

        /** @var list<object{seq: int}> $seqResult */
        $seqResult = DB::select("SELECT nextval('appointment_reference_seq') AS seq");
        $seq = $seqResult[0]->seq;
        $reference = sprintf('APT-%s-%04d', $startInLocalTz->format('Ymd'), $seq);

        $id = (string) Str::uuid();
        $startUtc = $start->setTimezone('UTC');
        $endUtc = $end->setTimezone('UTC');

        DB::table('appointments')->insert([
            'id' => $id,
            'reference' => $reference,
            'dealership_id' => $dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $serviceType->id,
            'technician_id' => $technician->id,
            'bay_id' => $bay->id,
            'status' => $status->value,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'expires_at' => $status === AppointmentStatus::HELD ? $now->addMinutes(5) : null,
            'version' => 1,
            'source' => 'STAFF',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customer->user_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $allocations = [
            [
                'resource_type' => ResourceType::TECHNICIAN,
                'resource_id' => $technician->id,
                'start_at' => $startUtc,
                'end_at' => $endUtc,
            ],
            [
                'resource_type' => ResourceType::BAY,
                'resource_id' => $bay->id,
                'start_at' => $startUtc,
                'end_at' => $endUtc,
            ],
            [
                'resource_type' => ResourceType::VEHICLE,
                'resource_id' => $vehicle->id,
                'start_at' => $startUtc,
                'end_at' => $endUtc,
            ],
        ];

        app(ResourceAllocationRepositoryInterface::class)->insertFor(
            $id,
            $dealership->id,
            $allocations,
            $now,
        );

        return Appointment::findOrFail($id);
    }

    public static function busyAllDay(Technician $technician, string $date): Appointment
    {
        $dealership = $technician->dealership ?? Dealership::findOrFail($technician->dealership_id);
        $weekday = (int) CarbonImmutable::parse("{$date} 00:00:00", $dealership->timezone)->isoWeekday();

        $oh = DB::table('dealership_opening_hours')
            ->where('dealership_id', $dealership->id)
            ->where('weekday', $weekday)
            ->first();

        $opensAt = $oh ? substr((string) $oh->opens_at, 0, 5) : '08:00';
        $closesAt = $oh ? substr((string) $oh->closes_at, 0, 5) : '17:00';

        $start = CarbonImmutable::parse("{$date} {$opensAt}", $dealership->timezone);
        $end = CarbonImmutable::parse("{$date} {$closesAt}", $dealership->timezone);

        $bay = Bay::where('dealership_id', $dealership->id)->firstOrFail();

        return self::create(
            dealership: $dealership,
            technician: $technician,
            bay: $bay,
            start: $start,
            end: $end,
        );
    }
}
