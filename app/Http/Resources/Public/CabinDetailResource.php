<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'images'          => $this->images->map(function ($img) {
                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
                $s3 = Storage::disk('s3');
                return [
                    'url'     => $s3->url($img->url),
                    'is_main' => (bool) $img->is_main,
                ];
            }),
        ];
    }
}
