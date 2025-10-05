<?php

namespace App\Http\Resources\Veterinarie;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VeterinarieResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $selected_segment_times = [];
        foreach ($this->resource->schedule_days as $schedule_day) {
           foreach ($schedule_day->schedule_joins as $schedule_join) {
            array_push($selected_segment_times,$schedule_join->veterinarie_schedule_hour_id.'-'.$schedule_day->day);
           }
        }
        $schedule_hour_veterinarie = [];
        foreach ($this->resource->schedule_days as $schedule_day) {
            foreach ($schedule_day->schedule_joins as $schedule_join) {
                array_push($schedule_hour_veterinarie,[
                    "id_seg" => $schedule_join->veterinarie_schedule_hour_id.'-'.$schedule_day->day,
                    "segment_time_id" => $schedule_join->veterinarie_schedule_hour_id,
                    "day" => $schedule_day->day,
                ]);
            }
         }
        return [
            "id" => $this->resource->id,
            'name' => $this->resource->name,
            'surname' => $this->resource->surname,
            "full_name" => $this->resource->name.' '.$this->resource->surname,
            'email' => $this->resource->email,
            "gender" => $this->resource->gender,
            'role_id' => $this->resource->role_id,
            "role" => [
                "name" => $this->resource->role->name,
            ],
            "role_name" => $this->resource->role->name,
            // http://127.0.0.1:8000/storage/imagen1.png
            'avatar' => $this->resource->avatar ? env("APP_URL")."storage/".$this->resource->avatar : NULL,
            "type_document" => $this->resource->type_document,
            "n_document" => $this->resource->n_document,
            "phone"=> $this->resource->phone,
            "designation"=> $this->resource->designation,
            "birthday" => $this->resource->birthday ? Carbon::parse($this->resource->birthday)->format("Y/m/d") : null,
            "selected_segment_times" => $selected_segment_times,
            "schedule_hour_veterinarie" => $schedule_hour_veterinarie,
        ];
    }
}
