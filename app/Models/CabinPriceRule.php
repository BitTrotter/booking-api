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
}
