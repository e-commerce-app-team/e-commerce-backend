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
            'order_number'   => 'ORD-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'total_price'    => (float) $this->total_price,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'shipping_pending' => (bool) $this->shipping_pending,
            'shipping_address_details' => $this->shipping_address_details,
            'status_timeline' => $this->status_timeline,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'shipped_at'     => $this->shipped_at?->toDateTimeString(),
            'delivered_at'   => $this->delivered_at?->toDateTimeString(),
            'sub_orders'     => SubOrderResource::collection($this->whenLoaded('subOrders')),
        ];
    }
}
