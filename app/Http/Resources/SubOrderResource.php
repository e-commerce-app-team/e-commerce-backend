<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SubOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
       $itemsTotal = (float) $this->items->sum(function ($item) {
            $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
            $quantity = (int) ($item->quantity ?? 0);
            $lineTotal = (float) ($item->total_price ?? 0);
            return $lineTotal > 0 ? $lineTotal : $unitPrice * $quantity;
        });
        $storedTotal = (float) ($this->total ?? $this->total_price ?? 0);
        $total = $storedTotal > 0
            ? $storedTotal
            : max(0, $itemsTotal - (float) ($this->discount_amount ?? 0) + (float) ($this->shipping_cost ?? 0));

       return [
            'id'          => $this->id,
            'seller_id'   => $this->seller_id,
            // إذا الـ status بقاعدة البيانات null ياخد 'pending' كقيمة افتراضية
            'status'      => $this->status ?? 'pending', 
            'shipping_method' => $this->shipping_method,
            'shipping_label' => $this->shipping_label,
            'shipping_cost' => $this->shipping_cost === null ? null : (float) $this->shipping_cost,
            'shipping_approved' => (bool) $this->shipping_approved,
            'shipping_approved_at' => $this->shipping_approved_at?->toDateTimeString(),
            'estimated_delivery' => $this->estimated_delivery,
            'shipment_state' => $this->shipment_state ?? 'pending',
            'escrow_amount' => (float) ($this->escrow_amount ?? 0),
            'escrow_release_at' => $this->escrow_release_at?->toDateTimeString(),
            'escrow_released_at' => $this->escrow_released_at?->toDateTimeString(),
            'delivery_confirmed_at' => $this->delivery_confirmed_at?->toDateTimeString(),
            'delivery_confirmation_type' => $this->delivery_confirmation_type,
            'items_total' => round($itemsTotal, 2),
            'total_price' => round($total, 2),
            'items'       => OrderItemResource::collection($this->whenLoaded('items')),
            'seller'      => $this->whenLoaded('seller', function () {
                return [
                    'id'         => $this->seller?->id,
                    'store_name' => $this->seller?->store_name ?? $this->seller?->name,
                    'store_logo' => $this->seller?->store_logo,
                ];
            }),
        ];
    }
}
