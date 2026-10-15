<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CabinSpecialPrice extends Model
{
    protected $fillable = ['date', 'price_per_night'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'price_per_night' => 'float',
    ];

    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class);
    }
}
