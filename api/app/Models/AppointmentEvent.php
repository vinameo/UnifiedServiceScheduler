<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $appointment_id
 * @property string $dealership_id
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property int $version
 * @property int|null $actor_user_id
 * @property string|null $actor_role
 * @property array<string, mixed> $data
 * @property CarbonImmutable $occurred_at
 * @property-read Appointment|null $appointment
 * @property-read Dealership|null $dealership
 * @property-read User|null $actorUser
 */
class AppointmentEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'appointment_events';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'appointment_id',
        'dealership_id',
        'type',
        'from_status',
        'to_status',
        'version',
        'actor_user_id',
        'actor_role',
        'data',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'occurred_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<Dealership, $this>
     */
    public function dealership(): BelongsTo
    {
        return $this->belongsTo(Dealership::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
