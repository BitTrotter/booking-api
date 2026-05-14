<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Resources\Json\JsonResource;

class CabinDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description_title' => $this->description_title,
            'description'       => $this->description,
            'check_in'          => $this->check_in,
            'check_out'         => $this->check_out,
            'price_per_night'   => (float) $this->price_per_night,
            'capacity'          => $this->capacity,
            'beds'              => $this->beds,
            'bathrooms'         => $this->bathrooms,
            'lat'               => $this->lat,
            'lng'               => $this->lng,
            'services'        => $this->services ?? [],
            'features'        => $this->features->map(fn($f) => [
                'name' => $f->name,
                'icon' => $f->icon,
            ]),
            'images'          => $this->images->map(fn($img) => [
                'url'     => $img->public_url,
                'is_main' => (bool) $img->is_main,
            ]),
        ];
    }
}
