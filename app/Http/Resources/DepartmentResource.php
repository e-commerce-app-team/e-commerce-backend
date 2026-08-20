<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // جلب لغة التطبيق الحالية المحدد بواسطة app()->setLocale()
        $locale = app()->getLocale();

        return [
            'id'             => $this->id,
            
            // 1. جلب الاسم باللغة المحددة مع وجود حماية للبيانات القديمة
            'name'           => $this->getTranslation('name', $locale, false) ?: $this->getRawOriginal('name'),
            
            'slug'           => $this->slug,
            'parent_id'      => $this->parent_id,
            'seller_id'      => $this->seller_id,
            'is_visible'     => $this->is_visible,
            'order_position' => $this->order_position,
            
            // 2. تحويل رابط الصورة والمنقوشات لرابط كامل (Full URL)
            'image_url'      => $this->image_url ? asset('storage/' . $this->image_url) : null,
            'icon_url'       => $this->icon_url ? asset('storage/' . $this->icon_url) : null,

            // 3. جلب الأبناء (Children/Subcategories) بشكل شجري إذا تم تحميل العلاقة
            'children'       => DepartmentResource::collection(
                $this->whenLoaded('recursiveChildren', function () {
                    return $this->recursiveChildren;
                }, $this->whenLoaded('children'))
            ),
        ];
    }
}