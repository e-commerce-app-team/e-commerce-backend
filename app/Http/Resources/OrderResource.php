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
            'total_price'    => $this->resolvedTotal(),
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'shipping_pending' => (bool) $this->shipping_pending,
            'shipping_ready_for_payment' => $this->subOrders->isEmpty()
                ? ! (bool) $this->shipping_pending
                : $this->subOrders->every(fn ($sub) => $sub->shipping_cost !== null && (bool) $sub->shipping_approved),
            'escrow_auto_release_at' => $this->subOrders->whereNull('escrow_released_at')->pluck('escrow_release_at')->filter()->min(),
            'shipping_address_details' => $this->shipping_address_details,
            'shipping_address_title' => $this->shipping_address_title,
            'customer_notes' => $this->customer_notes,
            'buyer' => $this->whenLoaded('buyer', function () {
                return [
                    'id' => $this->buyer?->id,
                    'first_name' => $this->buyer?->first_name,
                    'last_name' => $this->buyer?->last_name,
                    'phone' => $this->buyer?->phone,
                ];
            }),
            'status_timeline' => $this->status_timeline,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'shipped_at'     => $this->shipped_at?->toDateTimeString(),
            'delivered_at'   => $this->delivered_at?->toDateTimeString(),
            'sub_orders'     => SubOrderResource::collection($this->whenLoaded('subOrders')),
        ];
    }

    private function resolvedTotal(): float
    {
        $stored = (float) ($this->total_price ?? 0);
        if ($stored > 0) return round($stored, 2);

        return round((float) $this->subOrders->sum(function ($subOrder) {
            $items = (float) $subOrder->items->sum(function ($item) {
                $unit = (float) ($item->unit_price ?? $item->price ?? 0);
                return (float) ($item->total_price ?? ($unit * (int) $item->quantity));
            });
            return max(0, (float) ($subOrder->total ?? $subOrder->total_price ?? 0) ?: $items);
        }), 2);
    }
}
