<?php

namespace App\Models\booking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class bookingScheduleDay extends Model
{
    use HasFactory;
    protected $fillable = [
        'cabin_id',
        'day',
        'is_open',
    ];
    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set('America/Mexico_City');
        $this->attributes['created_at'] = $value ?? now();
    }
    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set('America/Mexico_City');
        $this->attributes['updated_at'] = $value ?? now();
    }
}
