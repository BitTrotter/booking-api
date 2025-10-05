<?php

namespace App\Models\Surgerie;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SurgeriePayment extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        "surgerie_id",
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

    public function surgerie(){
        return $this->belongsTo(Surgerie::class,"surgerie_id");
    }
}
