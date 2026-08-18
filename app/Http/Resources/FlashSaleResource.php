<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class FlashSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = Carbon::now();
        $expiresAt = $this->offer_expires_at ? Carbon::parse($this->offer_expires_at) : null;
        
        // حساب الثواني المتبقية حتى تاريخ الانتهاء
        $remainingSeconds = ($expiresAt && $expiresAt->isFuture()) 
            ? $now->diffInSeconds($expiresAt, false) 
            : 0;

        // استخراج الصورة الرئيسية
        $images = is_string($this->images) ? json_decode($this->images, true) : $this->images;
        $mainImage = is_array($images) && count($images) > 0 ? $images[0] : null;

        // حساب نسبة الخصم
        $discountPercentage = ($this->original_price > 0 && $this->offer_price < $this->original_price)
            ? round((($this->original_price - $this->offer_price) / $this->original_price) * 100)
            : 0;

        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'description'         => $this->description,
            'main_image'          => $mainImage ? asset('storage/' . $mainImage) : null,
            'original_price'      => (float) $this->original_price,
            'offer_price'         => (float) $this->offer_price,
            'discount_percentage' => $discountPercentage,
            'offer_expires_at'    => $this->offer_expires_at ? $this->offer_expires_at->toDateTimeString() : null,
            'remaining_seconds'   => $remainingSeconds, // العداد العكسي بالثواني
            'is_in_stock'         => $this->quantity > 0,
        ];
    }
}
