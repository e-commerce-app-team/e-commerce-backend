<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    // ============================================================
    // 📌 جلب الفواتير (مع فلتر النوع والشهر/السنة)
    // ============================================================

    /**
     * جلب جميع فواتير التاجر الحالي
     * مع دعم الفلترة بـ type و month و year
     */
    public function getInvoices(Request $request)
    {
        $user  = auth()->user();
        $query = Invoice::where('user_id', $user->id)->latest();

        // فلترة بنوع الفاتورة: order | commission
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // فلترة بالشهر والسنة
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', (int) $request->month)
                  ->whereYear('created_at',  (int) $request->year);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(15)
        ]);
    }

    /**
     * جلب الفاتورة الضريبية لطلب معين (wholesale فقط)
     */
    public function getOrderInvoice($orderId)
    {
        $user = auth()->user();

        if ($user->role !== 'wholesale') {
            return response()->json([
                'success' => false,
                'message' => 'الفواتير الضريبية متاحة لتجار الجملة فقط.'
            ], 403);
        }

        $invoice = Invoice::where('user_id', $user->id)
            ->where('order_id', $orderId)
            ->where('type', 'order')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $invoice
        ]);
    }

    /**
     * جلب فواتير عمولة المنصة (لكل التجار)
     */
    public function getCommissionInvoices(Request $request)
    {
        $user  = auth()->user();
        $query = Invoice::where('user_id', $user->id)
            ->where('type', 'commission')
            ->latest();

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', (int) $request->month)
                  ->whereYear('created_at',  (int) $request->year);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(15)
        ]);
    }

    /**
     * تقرير ضريبي شامل للشهر/السنة المحددة
     */
    public function getTaxReport(Request $request)
    {
        $user  = auth()->user();
        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year',  now()->year);

        // تقرير الفواتير الضريبية (wholesale فقط)
        $orderReport = Invoice::where('user_id', $user->id)
            ->where('type', 'order')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at',  $year)
            ->selectRaw('
                SUM(subtotal)           as total_sales,
                SUM(vat_amount)         as total_vat,
                SUM(commission_amount)  as total_commission,
                COUNT(*)                as invoice_count
            ')
            ->first();

        // تقرير فواتير العمولة (للجميع)
        $commissionReport = Invoice::where('user_id', $user->id)
            ->where('type', 'commission')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at',  $year)
            ->selectRaw('
                SUM(commission_amount) as total_commission_paid,
                COUNT(*)               as commission_count
            ')
            ->first();

        return response()->json([
            'success' => true,
            'period'  => ['month' => $month, 'year' => $year],
            'merchant_info' => [
                'store_name' => $user->store_name,
                'tax_number' => $user->tax_number,
                'cr_number'  => $user->commercial_registration_number,
            ],
            'report' => [
                'total_sales_before_tax' => round((float) ($orderReport->total_sales ?? 0), 2),
                'total_tax_collected'    => round((float) ($orderReport->total_vat ?? 0), 2),
                'order_invoice_count'    => (int) ($orderReport->invoice_count ?? 0),
                'total_commission_paid'  => round((float) ($commissionReport->total_commission_paid ?? 0), 2),
                'commission_count'       => (int) ($commissionReport->commission_count ?? 0),
            ]
        ]);
    }

    // ============================================================
    // 📌 الدوال القديمة (للتوافق مع السابق)
    // ============================================================

    /**
     * @deprecated استخدم getInvoices() بدلاً منه
     */
    public function getWholesaleInvoices(Request $request)
    {
        return $this->getInvoices($request);
    }
}
