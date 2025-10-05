<?php

namespace App\Http\Resources\Appointment;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $schedules = $this->resource->schedules->map(function($schedule) {
            return [
                "id" => $schedule->id,
                "veterinarie_schedule_hour_id" => $schedule->veterinarie_schedule_hour_id,
                "hour" => $schedule->schedule_hour->hour,
                "schedule_hour" => [
                    "hour_start" => $schedule->schedule_hour->hour_start,
                    "hour_end" => $schedule->schedule_hour->hour_end,
                    "hour" => $schedule->schedule_hour->hour,
                    "hour_start_format" => Carbon::parse(date("Y-m-d").' '.$schedule->schedule_hour->hour_start)->format("h:i A"),//8:00 AM o 9:00 AM 3:00 PM
                    "hour_end_format" => Carbon::parse(date("Y-m-d").' '.$schedule->schedule_hour->hour_end)->format("h:i A"),
                ],
            ];
        });
        return [
            "id" => $this->resource->id,
            "veterinarie_id" => $this->resource->veterinarie_id,
            "veterinarie" => [
                "full_name" => $this->resource->veterinarie->name.' '.$this->resource->veterinarie->surname,
                "role" => [
                    "name" => $this->resource->veterinarie->role->name,
                ],
            ],
            "pet_id" => $this->resource->pet_id,
            "pet" => [
                "id" => $this->resource->pet->id,
                "name" => $this->resource->pet->name,
                "specie" => $this->resource->pet->specie,
                "breed" => $this->resource->pet->breed,
                "photo" => env("APP_URL")."storage/".$this->resource->pet->photo,
                "owner" => [
                    "id" =>$this->resource->pet->owner->id,
                    "first_name"  =>$this->resource->pet->owner->first_name,
                    "last_name"  =>$this->resource->pet->owner->last_name,
                    "phone"  =>$this->resource->pet->owner->phone,
                    "n_document"  =>$this->resource->pet->owner->n_document,
                ]
            ],
            "day" => $this->resource->day,
            "date_appointment" => Carbon::parse($this->resource->date_appointment)->format("Y-m-d"),
            "reason" => $this->resource->reason,
            "reprogramar" => $this->resource->reprogramar,
            "state" => $this->resource->state,
            "user_id" => $this->resource->user_id,
            "user" => [
                "full_name" => $this->resource->user->name.' '.$this->resource->user->surname,
            ],
            "amount" => $this->resource->amount,
            "state_pay" => $this->resource->state_pay,
            "created_at" => $this->resource->created_at->format("Y-m-d h:i A"),
            "payments" => $this->resource->payments->map(function($payment) {
                return [
                    "id" => $payment->id,
                    "method_payment" => $payment->method_payment,
                    "amount" => $payment->amount,
                ];
            }),
            "schedules" => $schedules->sortBy("veterinarie_schedule_hour_id")->values()->all(),
        ];
    }
}
