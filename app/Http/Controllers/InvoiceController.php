<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;


class InvoiceController extends Controller
{
    // --- 1. جلب قائمة الفواتير لتاجر الجملة ---
    public function getInvoices()
    {
        $user = auth()->user();

        if ($user->role !== 'wholesale') {
            return response()->json(['message' => 'This section is only for wholesale users.'], 403);
        }

        $invoices = Invoice::where('user_id', $user->id)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $invoices
        ], 200);
    }

    // --- 2. تقرير شهري بإجمالي ضريبة القيمة المضافة (VAT Report) ---
    public function getVatReport(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'wholesale') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // تحديد الشهر والسنة المطلوبة، الافتراضي هو الشهر الحالي
        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);

        // حساب إجمالي الضرائب المجمعة في هذا الشهر
        $report = Invoice::where('user_id', $user->id)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->selectRaw('SUM(subtotal) as total_sales, SUM(vat_amount) as total_vat, SUM(total) as gross_total')
            ->first();

        return response()->json([
            'success' => true,
            'merchant_info' => [
                'company_name' => $user->store_name,
                'vat_number' => $user->tax_number,
                'commercial_registration' => $user->commercial_registration_number
            ],
            'period' => [
                'month' => $month,
                'year' => $year
            ],
            'report' => [
                'total_sales_before_vat' => round($report->total_sales ?? 0, 2),
                'total_vat_collected' => round($report->total_vat ?? 0, 2),
                'gross_total_sales' => round($report->gross_total ?? 0, 2),
            ]
        ], 200);
    }

    // --- 3. تحميل الفاتورة بصيغة PDF ---
   /*  public function downloadInvoicePDF($id)
    {
        $user = auth()->user();
        $invoice = Invoice::where('id', $id)->where('user_id', $user->id)->first();

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        // ملاحظة: لتوليد ملف PDF حقيقي، يُفضل استخدام مكتبة مثل barryvdh/laravel-dompdf
        // هنا نقوم بفحص ما إذا كان الملف مخزناً بالفعل ونقوم بتحميله للمستخدم
        if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
            return response()->download(storage_path("app/public/{$invoice->pdf_path}"), "Invoice-{$invoice->invoice_number}.pdf");
        }

        return response()->json(['message' => 'PDF file not generated yet or missing.'], 400);
    } */

}
