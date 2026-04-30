<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Cabin;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'created_by',
        'cabin_id',
        'start_date',
        'end_date',
        'guest_count',
        'email',
        'phone',
        'total_days',
        'total_price',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function cabin()
    {
        return $this->belongsTo(Cabin::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function guests()
    {
        return $this->hasMany(ReservationGuest::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
