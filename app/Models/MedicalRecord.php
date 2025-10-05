<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Pets\Pet;
use App\Models\Surgerie\Surgerie;
use Illuminate\Support\Facades\DB;
use App\Models\Appointment\Appointment;
use App\Models\Vaccination\Vaccination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicalRecord extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        "veterinarie_id",
        "pet_id",
        "event_type",
        "event_date",
        "appointment_id",
        "vaccination_id",
        "surgerie_id",
        "notes",
        "cron_state",
        // "created_at",
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

    public function veterinarie(){
        return $this->belongsTo(User::class,"veterinarie_id");
    }

    public function pet(){
        return $this->belongsTo(Pet::class,"pet_id");
    }

    public function appointment(){
        return $this->belongsTo(Appointment::class,"appointment_id");
    }

    public function vaccination(){
        return $this->belongsTo(Vaccination::class,"vaccination_id");
    }

    public function surgerie(){
        return $this->belongsTo(Surgerie::class,"surgerie_id");
    }

    public function scopeFilterMultiple($query,$type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$type_service){
        if($start_date && $end_date){
            if($type_date == 1){//POR FECHA DEL SERVICIO
                $query->whereBetween("event_date",[Carbon::parse($start_date)->format("Y-m-d")." 00:00:00",Carbon::parse($end_date)->format("Y-m-d")." 23:59:59"]);//Y-m-d h:i:s 00:00:00 23:59:59
            }else{//POR LA FECHA DE REGISTRO
                $query->whereBetween("created_at",[Carbon::parse($start_date)->format("Y-m-d")." 00:00:00",Carbon::parse($end_date)->format("Y-m-d")." 23:59:59"]);
            }
        }
        if($state_pay){//ESTADO DE PAGO
            if($type_service == 1){//APPOINTMENT
                $query->whereHas("appointment",function($subq) use($state_pay){
                    $subq->where("state_pay",$state_pay);
                });
            }
            if($type_service == 2){//VACCINATION
                $query->whereHas("vaccination",function($subq) use($state_pay){
                    $subq->where("state_pay",$state_pay);
                });
            }
            if($type_service == 3){//SURGERIE
                $query->whereHas("surgerie",function($subq) use($state_pay){
                    $subq->where("state_pay",$state_pay);
                });
            }
        }
        if($state){//ESTADO DEL SERVICIO
            if($type_service == 1){//APPOINTMENT
                $query->whereHas("appointment",function($subq) use($state){
                    $subq->where("state",$state);
                });
            }
            if($type_service == 2){//VACCINATION
                $query->whereHas("vaccination",function($subq) use($state){
                    $subq->where("state",$state);
                });
            }
            if($type_service == 3){//SURGERIE
                $query->whereHas("surgerie",function($subq) use($state){
                    $subq->where("state",$state);
                });
            }
            // $query->where("state",$state);
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
