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
        $isWholesale = auth()->check() && auth()->user()->role === 'wholesale';
        $isUpdate = $this->isMethod('PUT');
        // $isUpdate = $this->route('id') || $this->route('product');
        $productId = $this->route('id') ?? $this->route('product');
        return [
            // التحقق من مصفوفة المتغيرات القادمة من الواجهة
            'variants' => 'nullable|array',
            'variants.*.attributes' => 'required|array',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'required|integer|min:0',
            'variants.*.sku' => 'nullable|string',
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variants.*.is_active' => 'sometimes|boolean',

            // الحقول الأساسية للمنتج
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'description' => $isUpdate ? 'sometimes|required|string' : 'required|string',

            // التحقق من مصفوفة الصور الأساسية
            'images' => $isUpdate ? 'nullable|array|max:10' : 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',

            'video' => 'nullable|mimes:mp4,mov,ogg,qt|max:20480',

            'original_price' => $isUpdate ? 'sometimes|required|numeric|min:0' : 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0|lt:original_price',
            'offer_expires_at' => 'nullable|date|after:today',

            // -------------------------------------------------------------------------
            // قواعد التحقق الجديدة لحقول البيع بالجملة والشحن المجاني
            // -------------------------------------------------------------------------
            'is_free_shipping' => 'required|boolean',

            // حقول البيع بالجملة (تم تصحيح استخدام المتغير $isWholesale)
            // -------------------------------------------------------------------------
            'wholesale_price' => $isWholesale
                ? ($isUpdate ? 'sometimes|nullable|numeric|min:0|lt:original_price' : 'nullable|numeric|min:0|lt:original_price')
                : 'nullable|prohibited',

            'min_wholesale_qty' => $isWholesale
                ? ($isUpdate ? 'sometimes|required|integer|min:2' : 'required|integer|min:2')
                : 'nullable|prohibited',

            'warehouse_stock' => $isWholesale
                ? ($isUpdate ? 'sometimes|required|array' : 'required|array')
                : 'nullable|prohibited',
            // -------------------------------------------------------------------------

            // قاعدة الـ Unique للـ SKU تتجاهل المنتج الحالي أثناء التحديث بفضل الـ $productId
            'sku' => $isUpdate
                ? 'sometimes|required|string|unique:products,sku,' . $productId
                : 'required|string|unique:products,sku',

            'quantity' => $isUpdate ? 'sometimes|required|integer|min:0' : 'required|integer|min:0',
            'alert_threshold' => 'nullable|integer|min:0',

            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',

            'status' => $isUpdate ? 'sometimes|required|in:active,draft,hidden' : 'required|in:active,draft,hidden',
            // بعد
            'category_id' => 'nullable|exists:categories,id',
            'department_id' => 'required|exists:departments,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seller = auth()->user();
            if ($seller && (is_null($seller->latitude) || is_null($seller->longitude) || trim((string) $seller->detailed_address) === '')) {
                $validator->errors()->add('store_location', 'يجب تحديد موقع المتجر الرئيسي أولًا قبل إضافة أو نشر المنتجات.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'images.max' => 'You can upload a maximum of 10 images per product.',
            'offer_price.lt' => 'The offer price must be less than the original price.',
            'wholesale_price.lt' => 'The wholesale price must be less than the original price.',
            'offer_expires_at.after' => 'The offer expiry date must be a future date.',
            'sku.unique' => 'This SKU code is already in use for another product.',
            'wholesale_price.prohibited' => 'Wholesale price field is only allowed for Wholesale accounts.',
            'min_wholesale_qty.prohibited' => 'Minimum wholesale quantity field is only allowed for Wholesale accounts.',
            'warehouse_stock.prohibited' => 'Warehouse stock field is only allowed for Wholesale accounts.',

        ];
    }
}
