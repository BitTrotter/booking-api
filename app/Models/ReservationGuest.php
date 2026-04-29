<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationGuest extends Model
{
    protected $fillable = [
        'reservation_id',
        'full_name',
        'guest_type',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
