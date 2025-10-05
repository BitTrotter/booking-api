<?php

namespace App\Models\Vaccination;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\Veterinarie\VeterinarieScheduleHour;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VaccinationSchedule extends Model
{
    use HasFactory;
    protected $fillable = [
        "vaccination_id",
        "veterinarie_schedule_hour_id",
        "hour"
    ];

    public function setCreatedAtAttribute($value)
    {
    	date_default_timezone_set('America/Lima');
        $this->attributes["created_at"]= Carbon::now();
    }

    public function setUpdatedAtAttribute($value)
    {
    	date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"]= Carbon::now();
    }

    public function vaccination(){
        return $this->belongsTo(Vaccination::class,"vaccination_id");
    }
    public function schedule_hour(){
        return $this->belongsTo(VeterinarieScheduleHour::class,"veterinarie_schedule_hour_id");
    }
}
