<?php

namespace App\Models;

use Database\Factories\DealershipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dealership extends Model
{
    /** @use HasFactory<DealershipFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];
}
