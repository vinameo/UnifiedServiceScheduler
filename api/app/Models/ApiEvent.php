<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property CarbonImmutable $occurred_at
 * @property string $correlation_id
 * @property string|null $dealership_id
 * @property int|null $user_id
 * @property string|null $appointment_id
 * @property string $method
 * @property string $route
 * @property int|null $status
 * @property string|null $code
 * @property string|null $outcome
 * @property int|null $duration_ms
 * @property bool $idempotent_replay
 * @property int $allocation_retries
 * @property array<string, mixed> $detail
 * @property-read Dealership|null $dealership
 * @property-read Appointment|null $appointment
 * @property-read User|null $user
 */
class ApiEvent extends Model
{
    public $timestamps = false;

    protected $table = 'api_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'correlation_id',
        'dealership_id',
        'user_id',
        'appointment_id',
        'method',
        'route',
        'status',
        'code',
        'outcome',
        'duration_ms',
        'idempotent_replay',
        'allocation_retries',
        'detail',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'status' => 'integer',
            'duration_ms' => 'integer',
            'idempotent_replay' => 'boolean',
            'allocation_retries' => 'integer',
            'detail' => 'array',
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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
