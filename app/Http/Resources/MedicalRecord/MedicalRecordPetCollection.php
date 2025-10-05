<?php

namespace App\Http\Resources\MedicalRecord;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MedicalRecordPetCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "data" => MedicalRecordPetResource::collection($this->collection),
        ];
    }
}
