<?php

namespace App\Http\Resources\Veterinarie;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VeterinarieCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "data" => VeterinarieResource::collection($this->collection),
        ];
    }
}
