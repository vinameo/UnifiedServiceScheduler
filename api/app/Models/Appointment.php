<?php

namespace App\Models;

use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $reference
 * @property string $dealership_id
 * @property string $customer_id
 * @property string|null $vehicle_id
 * @property string $service_type_id
 * @property string $technician_id
 * @property string $bay_id
 * @property AppointmentStatus $status
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property CarbonImmutable|null $expires_at
 * @property int $version
 * @property string $source
 * @property bool $reminder_opt_in
 * @property int $created_by_user_id
 * @property string|null $cancel_reason
 * @property string|null $cancel_note
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property string|null $contact_required_reason
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Dealership|null $dealership
 * @property-read Customer|null $customer
 * @property-read Vehicle|null $vehicle
 * @property-read ServiceType|null $serviceType
 * @property-read Technician|null $technician
 * @property-read Bay|null $bay
 * @property-read User|null $createdByUser
 * @property-read User|null $cancelledByUser
 * @property-read Collection<int, AppointmentEvent> $events
 */
class Appointment extends Model
{
    use HasUuids;

    protected $table = 'appointments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'reference',
        'dealership_id',
        'customer_id',
        'vehicle_id',
        'service_type_id',
        'technician_id',
        'bay_id',
        'status',
        'start_at',
        'end_at',
        'expires_at',
        'version',
        'source',
        'reminder_opt_in',
        'created_by_user_id',
        'cancel_reason',
        'cancel_note',
        'cancelled_at',
        'cancelled_by_user_id',
        'contact_required_reason',
        'checked_in_at',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'version' => 'integer',
            'reminder_opt_in' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Dealership, $this>
     */
    public function dealership(): BelongsTo
    {
        return $this->belongsTo(Dealership::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * @return BelongsTo<Technician, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    /**
     * @return BelongsTo<Bay, $this>
     */
    public function bay(): BelongsTo
    {
        return $this->belongsTo(Bay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return HasMany<AppointmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class, 'appointment_id')
            ->orderBy('occurred_at', 'asc')
            ->orderBy('id', 'asc');
    }
}
