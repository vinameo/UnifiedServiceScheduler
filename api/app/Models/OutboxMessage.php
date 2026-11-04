<?php

namespace App\Models;

use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $event_id
 * @property string $dealership_id
 * @property string|null $appointment_id
 * @property OutboxEventType $event_type
 * @property OutboxChannel $channel
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property CarbonImmutable $next_attempt_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $sent_at
 * @property string|null $dedupe_key
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Dealership $dealership
 * @property-read Appointment|null $appointment
 */
class OutboxMessage extends Model
{
    use HasUuids;

    protected $table = 'outbox_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'event_id',
        'dealership_id',
        'appointment_id',
        'event_type',
        'channel',
        'payload',
        'status',
        'attempts',
        'next_attempt_at',
        'last_error',
        'sent_at',
        'dedupe_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => OutboxEventType::class,
            'channel' => OutboxChannel::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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
     * D-11.9 / TC-11-15: Display label for status.
     */
    public function displayStatus(): string
    {
        return match ($this->status) {
            'PENDING' => $this->attempts > 0 ? 'Retrying' : 'Pending',
            'FAILED' => 'Failed',
            'SENT' => 'Sent',
            'SKIPPED' => 'Skipped',
            default => ucfirst(strtolower((string) $this->status)),
        };
    }
}
