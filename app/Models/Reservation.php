<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Cabin;

class Reservation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            $reservation->public_id ??= (string) Str::uuid();
            $reservation->public_code ??= self::generatePublicCode();
        });
    }

    public static function generatePublicCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    protected $fillable = [
        'user_id',
        'created_by',
        'cabin_id',
        'start_date',
        'end_date',
        'guest_count',
        'full_name',
        'email',
        'phone',
        'total_days',
        'total_price',
        'status',
        'confirmation_token',
        'payment_method',
        'payment_reference',
        'notes',
        'amount_paid',
    ];

    protected $hidden = [
        'confirmation_token',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'amount_paid' => 'decimal:2',
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
