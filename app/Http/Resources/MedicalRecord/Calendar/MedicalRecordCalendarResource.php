<?php

namespace App\Http\Resources\MedicalRecord\Calendar;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordCalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = null;
        $calendar = "";
        if($this->resource->appointment_id){
            $resource = $this->resource->appointment;
            $calendar = 'Appointment';
        }
        if($this->resource->vaccination_id){
            $resource = $this->resource->vaccination;
            $calendar = 'Vaccination';
        }
        if($this->resource->surgerie_id){
            $resource = $this->resource->surgerie;
            $calendar = 'Surgeries';
        }
        $hour_start = $resource->schedules->first()->schedule_hour->hour_start;
        $hour_end = $resource->schedules->first()->schedule_hour->hour_end;

        return [
            "id" => $this->resource->id,
            "title" => $resource->pet->name,//.' '.Carbon::parse(date("Y-m-d").' '.$hour_start)->format("h:i A").' '.Carbon::parse(date("Y-m-d").' '.$hour_end)->format("h:i A"),
            "start" => Carbon::parse(Carbon::parse($this->resource->event_date)->format("Y-m-d").' '.$hour_start)->format("Y-m-d h:i:s"),
            "end" => Carbon::parse(Carbon::parse($this->resource->event_date)->format("Y-m-d").' '.$hour_end)->format("Y-m-d h:i:s"),
            "allDay" => false,
            "url" => '',
            "extendedProps" => [
                "calendar" => $calendar,
                "description" => $calendar == 'Surgeries' ? $resource->medical_notes : $resource->reason,
                "notes" => $this->resource->notes,
                "day" => $resource->day,
                "state" => $resource->state,
                "amount" => $resource->amount,
                "veterinarie" => [
                    "full_name" => $resource->veterinarie->name.' '.$resource->veterinarie->surname,
                    "role" => [
                        "name" => $resource->veterinarie->role->name,
                    ],
                ],
                "pet" => [
                    "id" => $resource->pet->id,
                    "name" => $resource->pet->name,
                    "specie" => $resource->pet->specie,
                    "breed" => $resource->pet->breed,
                    "photo" => env("APP_URL")."storage/".$resource->pet->photo,
                    "owner" => [
                        "id" =>$resource->pet->owner->id,
                        "first_name"  =>$resource->pet->owner->first_name,
                        "last_name"  =>$resource->pet->owner->last_name,
                        "phone"  =>$resource->pet->owner->phone,
                        "n_document"  =>$resource->pet->owner->n_document,
                    ]
                ],
            ],
        ];
    }
}
