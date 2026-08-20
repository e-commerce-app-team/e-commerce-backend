<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
      $unitPrice = (float) ($this->unit_price ?? $this->price ?? 0);
        $quantity  = (int) ($this->quantity ?? 1);

        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            // إذا كان total_price صفر بالداتا بيز، بيحسبه تلقائياً
            'total_price' => $this->total_price > 0 ? (float)$this->total_price : ($unitPrice * $quantity),
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
