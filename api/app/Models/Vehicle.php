<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property string $licence_plate
 * @property string $plate_key
 * @property string|null $vin
 * @property string $make
 * @property string $model
 * @property int|null $year
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if (isset($vehicle->licence_plate)) {
                $vehicle->licence_plate = trim(strtoupper($vehicle->licence_plate));
                if (empty($vehicle->plate_key)) {
                    $vehicle->plate_key = static::normalizePlateKey($vehicle->licence_plate);
                } else {
                    $vehicle->plate_key = static::normalizePlateKey($vehicle->plate_key);
                }
            }
            if ($vehicle->vin !== null) {
                $trimmed = strtoupper(trim($vehicle->vin));
                $vehicle->vin = $trimmed !== '' ? $trimmed : null;
            }
        });
    }

    /**
     * Derive the uppercase alphanumeric plate key used for uniqueness and search.
     */
    public static function normalizePlateKey(string $plate): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
