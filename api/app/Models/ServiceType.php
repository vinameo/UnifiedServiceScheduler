<?php

namespace App\Models;

use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $dealership_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $required_level
 * @property BayType $required_bay_type
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Dealership $dealership
 */
class ServiceType extends Model
{
    use HasUuids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'required_level' => 'integer',
            'required_bay_type' => BayType::class,
            'active' => 'boolean',
        ];
    }

    public function requiredLevel(): Level
    {
        return Level::from($this->required_level);
    }

    /**
     * @return BelongsTo<Dealership, $this>
     */
    public function dealership(): BelongsTo
    {
        return $this->belongsTo(Dealership::class);
    }
}
