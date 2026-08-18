<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * دالة مساعدة لفك واستخراج النص المترجم بذكاء
     */
    private function parseTranslation($attribute, $locale)
    {
        // 1. محاولة جلب الترجمة من Spatie أولاً
        if (method_exists($this->resource, 'getTranslation')) {
            $value = $this->getTranslation($attribute, $locale, false);
            if ($value) return $value;
        }

        // 2. قراءة القيمة الخام
        $raw = $this->getRawOriginal($attribute) ?? $this->{$attribute};

        // إذا كانت القيمة نص JSON (مثل "{\"ar\": \"...\"}") نقوم بفكها
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded[$locale] 
                    ?? $decoded['ar'] 
                    ?? $decoded['en'] 
                    ?? reset($decoded);
            }
            return $raw; // نص عادي قديم
        }

        if (is_array($raw)) {
            return $raw[$locale] ?? $raw['ar'] ?? $raw['en'] ?? reset($raw);
        }

        return $raw;
    }

    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        // معالجة روابط الصور
        $images = is_array($this->images) 
            ? array_map(fn($img) => str_starts_with($img, 'http') ? $img : asset('storage/' . $img), $this->images) 
            : [];

        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'category_id'      => $this->category_id,
            'department_id'    => $this->department_id,

            // استخراج الاسم والوصف المترجم نظيفاً
            'name'             => $this->parseTranslation('name', $locale),
            'description'      => $this->parseTranslation('description', $locale),

            'images'           => $images,
            'video_url'        => $this->video_url,
            'original_price'   => $this->original_price,
            'wholesale_price'  => $this->wholesale_price,
            'offer_price'      => $this->offer_price,
            'sku'              => $this->sku,
            'quantity'         => $this->quantity,
            'status'           => $this->status,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,

            'variants'         => $this->whenLoaded('variants'),
            'seller'           => $this->whenLoaded('seller'),
        ];
    }
}