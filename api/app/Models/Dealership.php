<?php

namespace App\Models;

use Database\Factories\DealershipFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $branch_name
 * @property string $timezone
 * @property string $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, DealershipOpeningHour> $openingHours
 * @property-read Collection<int, ServiceType> $serviceTypes
 * @property-read Collection<int, Bay> $bays
 * @property-read Collection<int, Technician> $technicians
 */
class Dealership extends Model
{
    /** @use HasFactory<DealershipFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<DealershipOpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(DealershipOpeningHour::class);
    }

    /**
     * @return HasMany<ServiceType, $this>
     */
    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    /**
     * @return HasMany<Bay, $this>
     */
    public function bays(): HasMany
    {
        return $this->hasMany(Bay::class);
    }

    /**
     * @return HasMany<Technician, $this>
     */
    public function technicians(): HasMany
    {
        return $this->hasMany(Technician::class);
    }
}
