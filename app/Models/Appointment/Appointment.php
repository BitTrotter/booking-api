<?php

namespace App\Models\Appointment;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Pets\Pet;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\MedicalRecord\AppointmentFactory;

class Appointment extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        "veterinarie_id",
        "pet_id",
        "day",
        "date_appointment",
        "reason",
        "reprogramar",
        "state",
        "user_id",
        "amount",
        "state_pay",
        // "created_at",
    ];

    public function setCreatedAtAttribute($value)
    {
    	date_default_timezone_set('America/Lima');
        $this->attributes["created_at"]= Carbon::now();
    }
    protected static function newFactory()
    {
        return AppointmentFactory::new();
    }
    
    public function setUpdatedAtAttribute($value)
    {
    	date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"]= Carbon::now();
    }

    public function veterinarie(){
        return $this->belongsTo(User::class,"veterinarie_id");
    }
    public function pet(){
        return $this->belongsTo(Pet::class,"pet_id");
    }
    public function user() {
        return $this->belongsTo(User::class,"user_id");
    }
    public function payments() {
        return $this->hasMany(AppointmentPayment::class);
    }
    public function schedules() {
        return $this->hasMany(AppointmentSchedule::class);
    }
    public function medical_record(){
        return $this->hasOne(MedicalRecord::class,"appointment_id");
    }
    public function scopeFilterMultiple($query,$type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$user = null){
        if($start_date && $end_date){
            if($type_date == 1){//POR FECHA DE CITA
                $query->whereBetween("date_appointment",[Carbon::parse($start_date)->format("Y-m-d")." 00:00:00",Carbon::parse($end_date)->format("Y-m-d")." 23:59:59"]);//Y-m-d h:i:s 00:00:00 23:59:59
            }else{//POR LA FECHA DE REGISTRO
                $query->whereBetween("created_at",[Carbon::parse($start_date)->format("Y-m-d")." 00:00:00",Carbon::parse($end_date)->format("Y-m-d")." 23:59:59"]);
            }
        }
        if($user && strpos(strtolower($user->role->name),'veterinario') !== false){
            //CUENTOS PARA ÑIÑOS
            //CUESTOS
            $query->where("veterinarie_id",$user->id);
        }
        if($state_pay){
            $query->where("state_pay",$state_pay);
        }
        if($state){
            $query->where("state",$state);
        }
        if($specie){
            $query->whereHas("pet",function($q) use($specie){
                $q->where("specie",$specie);
            });
        }
        if($search_pets){
            $query->whereHas("pet",function($q) use($search_pets){
                $q->where("name","ilike","%".$search_pets."%");
            });
        }
        if($search_vets){
            $query->whereHas("veterinarie",function($q) use($search_vets){
                $q->where(DB::raw("users.name || ' ' || COALESCE(users.surname,'') || ' ' || COALESCE(users.phone,'') || ' ' || COALESCE(users.n_document,'') || ' ' || users.email"),"ilike","%".$search_vets."%");
            });
        }
        return $query;
    }
}
