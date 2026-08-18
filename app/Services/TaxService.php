<?php

namespace App\Services;

use App\Models\Order;

class TaxService
{
    // ============================================================
    // 📌 نسب الضريبة الافتراضية السورية (للرجوع إليها)
    // ============================================================
    // 0%  → غذاء، أدوات زراعية
    // 5%  → الأغلبية (إلكترونيات، أزياء، منزل، رياضة...)
    // 10% → الصحة والجمال (تصنيف واحد ثابت)، السيارات وقطع الغيار
    // 15% → رخام وغرانيت

    /**
     * حساب الضريبة لقائمة منتجات طلب معين.
     *
     * كل عنصر في $items يجب أن يحتوي على:
     *   - 'product'    : نموذج Product (مع علاقة category محمّلة)
     *   - 'quantity'   : int عدد القطع
     *   - 'base_price' : float السعر الأساسي للوحدة (قبل الضريبة)
     *
     * @param array $items
     * @return array ['subtotal', 'tax_amount', 'total', 'breakdown']
     */
    public function calculateOrderTax(array $items): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;
        $breakdown = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $qty = (int) $item['quantity'];
            $basePrice = (float) $item['base_price'];

            // جلب النسبة الضريبية الفعلية (0 إذا كان المنتج معفى)
            $taxRate = $product->effectiveTaxRate(); // مثلاً 5.0 أو 10.0

            $itemSubtotal = $basePrice * $qty;
            $itemTax = round($itemSubtotal * ($taxRate / 100), 2);

            $subtotal += $itemSubtotal;
            $taxAmount += $itemTax;

            $breakdown[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'unit_price' => $basePrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTax,
                'tax_exempt' => (bool) $product->tax_exempt,
                'tax_label' => $product->taxLabel(),
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($subtotal + $taxAmount, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * جلب نسبة عمولة المنصة حسب دور التاجر
     * 🔥 يقرأ فقط من جدول orders
     */
    public function getCommissionRate(string $sellerRole, int $orderId): float
    {
        // 🔥 القراءة من الطلب فقط
        $order = Order::find($orderId);
        
        if ($order && $order->commission_rate_snapshot > 0) {
            return (float) $order->commission_rate_snapshot;
        }

        // 🔥 إذا لم توجد نسبة في الطلب، استخدم القيم الافتراضية
        return $sellerRole === 'wholesale' ? 5 : 10;
    }

    /**
     * حساب عمولة المنصة وصافي التاجر
     * 🔥 يقرأ من orders فقط
     *
     * @return array ['rate', 'commission', 'net']
     */
    public function calculateCommission(float $amount, string $sellerRole, int $orderId): array
    {
        $rate = $this->getCommissionRate($sellerRole, $orderId);
        $commission = round($amount * ($rate / 100), 2);

        return [
            'rate' => $rate,
            'commission' => $commission,
            'net' => round($amount - $commission, 2),
        ];
    }
}