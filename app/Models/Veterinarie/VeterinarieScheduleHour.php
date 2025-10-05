<?php

namespace App\Models\Veterinarie;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VeterinarieScheduleHour extends Model
{
    use HasFactory;
    protected $fillable = [
        "hour_start",
        "hour_end",
        "hour",
    ];
}
