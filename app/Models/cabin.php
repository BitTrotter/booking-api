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
        'status'
    ];

    protected $casts = [
        'services' => 'array',
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'cabin_feature', 'cabin_id', 'feature_id');
    }

    public function images()
    {
        return $this->hasMany(CabinImage::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
       public function priceRules()
    {
        return $this->hasMany(CabinPriceRule::class);
    }
}
