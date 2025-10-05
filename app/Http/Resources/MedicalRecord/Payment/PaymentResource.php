<?php

namespace App\Http\Resources\MedicalRecord\Payment;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = null;
        if($this->resource->appointment_id){
            $resource = $this->resource->appointment;
        }
        if($this->resource->vaccination_id){
            $resource = $this->resource->vaccination;
        }
        if($this->resource->surgerie_id){
            $resource = $this->resource->surgerie;
        }
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
            "event_date" => Carbon::parse($this->resource->event_date)->format("Y-m-d"),
            "notes" => $this->resource->notes,
            "created_at" => $this->resource->created_at->format("Y-m-d h:i A"),
            "event_type" => $this->event_type,
            "appointment_id" => $this->resource->appointment_id,
            "vaccination_id" => $this->resource->vaccination_id,
            "surgerie_id" => $this->resource->surgerie_id,

            "state" => $resource->state,
            "amount" => $resource->amount,
            "state_pay" => $resource->state_pay,
            "payment_total" => $resource->payments->sum("amount"),
            "payments" => $resource->payments->map(function($payment) {
                return [
                    "id" => $payment->id,
                    "method_payment" => $payment->method_payment,
                    "amount" => $payment->amount,
                ];
            }),
        ];
    }
}
