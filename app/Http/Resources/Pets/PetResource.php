<?php

namespace App\Http\Resources\Pets;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->resource->id,
            'name' => $this->resource->name,
            'specie' => $this->resource->specie,
            'breed' => $this->resource->breed,
            'dirth_date' => $this->resource->dirth_date ? Carbon::parse($this->resource->dirth_date)->format("Y-m-d") : null,
            'gender' => $this->resource->gender,
            'color' => $this->resource->color,
            'weight' => $this->resource->weight,
            'photo' => env("APP_URL")."storage/".$this->resource->photo,
            'medical_notes' => $this->resource->medical_notes,
            'owner_id'  => $this->resource->owner_id,
            "n_appointment" => $this->resource->appointments->count(),
            "n_vaccination" =>$this->resource->vaccinations->count(),
            "n_surgerie" =>$this->resource->surgeries->count(),
            'owner' => [
               'first_name' => $this->resource->owner->first_name,
                'last_name' => $this->resource->owner->last_name,
                'email' => $this->resource->owner->email,
                'phone' => $this->resource->owner->phone,
                'address' => $this->resource->owner->address,
                'city' => $this->resource->owner->city,
                'emergency_contact' => $this->resource->owner->emergency_contact,
                'type_document' => $this->resource->owner->type_document,
                'n_document' => $this->resource->owner->n_document,
            ],
        ];
    }
}
