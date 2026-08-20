<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
   return [
            'id'             => $this->id,
            'total_price'    => (float) $this->total_price,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'sub_orders'     => SubOrderResource::collection($this->whenLoaded('subOrders')),
        ];
    }
}
