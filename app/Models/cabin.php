<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cabin extends Model
{
    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected static function booted(): void
    {
        static::saving(function (Cabin $cabin) {
            if ($cabin->isDirty('weekly_prices') && $cabin->weekly_prices) {
                $cabin->weekly_prices = array_map(fn ($price) => (float) $price, $cabin->weekly_prices);
                $cabin->price_per_night = min($cabin->weekly_prices);
            } elseif ($cabin->isDirty('price_per_night') || !$cabin->weekly_prices) {
                $cabin->weekly_prices = array_fill_keys(self::WEEKDAYS, (float) $cabin->price_per_night);
            }
        });
    }

    protected $fillable = [
        'name',
        'description_title',
        'description',
        'check_in',
        'check_out',
        'price_per_night',
        'weekly_prices',
        'capacity',
        'beds',
        'bathrooms',
        'services',
        'status',
        'lat',
        'lng'
    ];

    protected $casts = [
        'services' => 'array',
        'weekly_prices' => 'array',
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
}
