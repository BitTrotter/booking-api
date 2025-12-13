<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Cabin;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'cabin_id',
        'start_date',
        'end_date',
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
}
