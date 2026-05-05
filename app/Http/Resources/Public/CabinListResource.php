<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Resources\Json\JsonResource;

class CabinListResource extends JsonResource
{
    public function toArray($request): array
    {
        $mainImage = $this->images->firstWhere('is_main', true) ?? $this->images->first();

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'price_per_night' => (float) $this->price_per_night,
            'capacity'        => $this->capacity,
            'beds'            => $this->beds,
            'bathrooms'       => $this->bathrooms,
            'cover_image'     => $mainImage ? asset('storage/' . $mainImage->url) : null,
            'features'        => $this->features->map(fn($f) => [
                'name' => $f->name,
                'icon' => $f->icon,
            ]),
        ];
    }
}
