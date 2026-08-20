<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id'        => $this->id,
            // جلب الترجمة بحسب اللغة الحالية مع وضع قيمة احتياطية للبيانات القديمة
            'name'      => $this->getTranslation('name', $locale, false) ?: $this->getRawOriginal('name'),
            'slug'      => $this->slug,
            'seller_id' => $this->seller_id,
            'children'  => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}