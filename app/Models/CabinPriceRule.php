<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabinPriceRule extends Model
{
    protected $fillable = [
        'cabin_id',
        'start_date',
        'end_date',
        'price_per_night',
        'status',
        'type'
    ];

    protected $casts = [
        'days' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
    ];
}
