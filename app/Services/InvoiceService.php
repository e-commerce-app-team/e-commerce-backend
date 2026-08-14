<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;

class InvoiceService
{
    // ============================================================
    // 📌 توليد الفواتير
    // ============================================================

    /**
     * توليد فاتورة ضريبية لطلب مكتمل.
     * ⚠️ هذه الفاتورة للـ wholesale ONLY (لأن لديه سجل تجاري).
     *
     * @return Invoice|null  null إذا كان التاجر vendor وليس wholesale
     */
    public function generateOrderInvoice(Order $order): ?Invoice
    {
        $seller = $order->seller;

        // فواتير الطلبات الضريبية لـ wholesale فقط
        if ($seller->role !== 'wholesale') {
            return null;
        }

        // تجنب تكرار الفاتورة لنفس الطلب
        $existing = Invoice::where('order_id', $order->id)
            ->where('type', 'order')
            ->first();
        if ($existing) {
            return $existing;
        }

        return Invoice::create([
            'user_id'           => $seller->id,
            'order_id'          => $order->id,
            'type'              => 'order',
            'invoice_number'    => $this->generateInvoiceNumber('INV'),
            'seller_name'       => $seller->store_name ?? ($seller->first_name . ' ' . $seller->last_name),
            'seller_tax_number' => $seller->tax_number ?? null,
            'seller_cr'         => $seller->commercial_registration_number ?? null,
            'subtotal'          => $order->subtotal_before_tax ?? 0,
            'vat_amount'        => $order->tax_amount ?? 0,
            'commission_amount' => $order->platform_commission ?? 0,
            'total'             => $order->total_price,
            'line_items'        => $order->tax_breakdown ?? [],
            'status'            => 'issued',
            'notes'             => null,
        ]);
    }

    /**
     * توليد فاتورة عمولة منصة لأي تاجر (vendor أو wholesale).
     * تُصدر عند اكتمال الطلب وتحرير الأموال.
     */
    public function generateCommissionInvoice(Order $order): Invoice
    {
        $seller = $order->seller;

        // تجنب تكرار فاتورة العمولة لنفس الطلب
        $existing = Invoice::where('order_id', $order->id)
            ->where('type', 'commission')
            ->first();
        if ($existing) {
            return $existing;
        }

        $commissionRate   = $order->commission_rate_snapshot ?? 0;
        $commissionAmount = $order->platform_commission ?? 0;

        return Invoice::create([
            'user_id'           => $seller->id,
            'order_id'          => $order->id,
            'type'              => 'commission',
            'invoice_number'    => $this->generateInvoiceNumber('COM'),
            'seller_name'       => $seller->store_name ?? ($seller->first_name . ' ' . $seller->last_name),
            'seller_tax_number' => $seller->tax_number ?? null,
            'seller_cr'         => $seller->commercial_registration_number ?? null,
            'subtotal'          => $order->total_price,
            'vat_amount'        => 0,
            'commission_amount' => $commissionAmount,
            'total'             => round($order->total_price - $commissionAmount, 2),
            'line_items'        => null,
            'status'            => 'issued',
            'notes'             => 'عمولة منصة ' . $commissionRate . '% عن الطلب #' . $order->id,
        ]);
    }

    // ============================================================
    // 📌 دوال مساعدة
    // ============================================================

    /**
     * توليد رقم فاتورة فريد بالصيغة: INV-2026-00001
     */
    protected function generateInvoiceNumber(string $prefix = 'INV'): string
    {
        $year  = date('Y');
        $count = Invoice::whereYear('created_at', $year)->count() + 1;
        return $prefix . '-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
