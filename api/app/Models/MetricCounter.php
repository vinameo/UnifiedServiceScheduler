<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $labels
 * @property int $value
 * @property CarbonImmutable $updated_at
 */
class MetricCounter extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'metric_counters';

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'labels',
        'value',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
