<?php

namespace App\Models;

use Database\Factories\DealershipFactory;
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
}
