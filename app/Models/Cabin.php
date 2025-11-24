<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cabin extends Model
{
      protected $fillable = [
        'name',
        'description',
        'price_per_night',
        'capacity',
        'beds',
        'bathrooms',
        'services',
        'status',
    ];

    protected $casts = [
        'services' => 'array', // convierte automáticamente JSON a array
    ];
}
