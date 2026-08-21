<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SubOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
       return [
            'id'          => $this->id,
            'seller_id'   => $this->seller_id,
            // إذا الـ status بقاعدة البيانات null ياخد 'pending' كقيمة افتراضية
            'status'      => $this->status ?? 'pending', 
            'shipping_method' => $this->shipping_method,
            'shipping_label' => $this->shipping_label,
            'shipping_cost' => $this->shipping_cost === null ? null : (float) $this->shipping_cost,
            'estimated_delivery' => $this->estimated_delivery,
            // إذا السعر صفر بالـ SubOrder تحسبه من مجموع المنتجات بداخلها
            'total_price' => (float) ($this->total ?? $this->items->sum(fn($i) => ($i->unit_price ?? $i->price) * $i->quantity)),
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
