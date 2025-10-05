<?php

namespace App\Http\Resources\User;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
        ];
    }
}
