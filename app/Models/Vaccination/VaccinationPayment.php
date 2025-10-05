<?php

namespace App\Models\Vaccination;

use Carbon\Carbon;
use App\Models\Vaccination\Vaccination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VaccinationPayment extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        "vaccination_id",
        "method_payment",
        "amount",
        // "created_at"
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
}
