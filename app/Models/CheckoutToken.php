<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutToken extends Model
{
    protected $fillable = [
        'reservation_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
