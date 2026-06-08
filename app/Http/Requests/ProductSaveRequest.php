<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->role === 'vendor' || auth()->user()->role === 'wholesale');
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            // التحقق من مصفوفة المتغيرات القادمة من الواجهة
            'variants'             => 'nullable|array',
            'variants.*.attributes' => 'required|array', // للتأكد من إرسال الخصائص كـ [color, size]
            'variants.*.price'     => 'nullable|numeric|min:0',
            'variants.*.quantity'  => 'required|integer|min:0',
            'variants.*.sku'       => 'nullable|string',
            'variants.*.image'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // صورة مستقلة
            'variants.*.is_active' => 'sometimes|boolean',
            //
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'description' => $isUpdate ? 'sometimes|required|string' : 'required|string',

            // التحقق من مصفوفة الصور (حتى 10 صور)
            'images' => $isUpdate ? 'nullable|array|max:10' : 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',

            'video' => 'nullable|mimes:mp4,mov,ogg,qt|max:20480', // فيديو اختياري

            'original_price' => $isUpdate ? 'sometimes|required|numeric|min:0' : 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0|lt:original_price',
            'offer_expires_at' => 'nullable|date|after:today',

            'sku' => $isUpdate ? 'sometimes|required|string|unique:products,sku,' . $this->route('product') : 'required|string|unique:products,sku',
            'quantity' => $isUpdate ? 'sometimes|required|integer|min:0' : 'required|integer|min:0',
            'alert_threshold' => 'nullable|integer|min:0',

            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',

            'status' => $isUpdate ? 'sometimes|required|in:active,draft,hidden' : 'required|in:active,draft,hidden',
            'category_id' => $isUpdate ? 'sometimes|required|exists:categories,id' : 'required|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'You can upload a maximum of 10 images per product.',
            'offer_price.lt' => 'The offer price must be less than the original price.',
            'offer_expires_at.after' => 'The offer expiry date must be a future date.',
            'sku.unique' => 'This SKU code is already in use for another product.',
        ];
    }
}
