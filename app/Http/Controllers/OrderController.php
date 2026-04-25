<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function markAsDelivered($orderId)
    {
        return DB::transaction(function () use ($orderId) {

            // 1. جلب الطلب مع بيانات المشتري والمزود (بائع أو جملة)
            $order = Order::with(['buyer', 'vendor'])->findOrFail($orderId);

            // 2. منع تكرار العملية
            if ($order->status === 'delivered') {
                return response()->json(['message' => 'Order already delivered and processed.'], 400);
            }

            // 3. تحديث حالة الطلب
            $order->update(['status' => 'delivered']);

            // 4. تحديد العمولة بناءً على رتبة البائع (دعم الجملة)
            $vendor = $order->vendor;
            $totalAmount = $order->total_price;

            // إذا كان بائع جملة نأخذ 5% وإذا بائع عادي 10%
            $commissionRate = ($vendor->role === 'wholesale') ? 0.05 : 0.10;

            $adminCommission = $totalAmount * $commissionRate;
            $vendorProfit = $totalAmount - $adminCommission;

            // 5. التحقق من الرتبة وإضافة الرصيد
            if ($vendor->role === 'vendor' || $vendor->role === 'wholesale') {

                $vendor->balance += $vendorProfit;
                $vendor->save();

                // 6. توثيق العملية في السجلات المالية
                Transaction::create([
                    'user_id' => $vendor->id,
                    'type' => 'deposit',
                    'amount' => $vendorProfit,
                    'description' => "Earnings from Order #{$order->id} (Role: {$vendor->role})"
                ]);

                return response()->json([
                    'message' => 'Order delivered. Funds transferred successfully.',
                    'vendor_earned' => $vendorProfit,
                    'commission_taken' => $adminCommission
                ]);
            }

            return response()->json(['message' => 'User is not a valid seller.'], 400);
        });
    }

    public function store(Request $request)
    {
        // 1. المشتري هو المستخدم الحالي
        $buyer = auth()->user();

        // 2. التحقق من البيانات (نحتاج رقم البائع والمبلغ)
        $request->validate([
            'vendor_id' => 'required|exists:users,id',
            'total_price' => 'required|numeric'
        ]);

        // 3. عملية الربط وإنشاء الطلب
        $order = Order::create([
            'user_id' => $buyer->id,       // المشتري
            'vendor_id' => $request->vendor_id, // البائع (يأتي من واجهة المحل)
            'total_price' => $request->total_price,
            'status' => 'pending',
        ]);

        // 4. النتيجة: أصبح عندك الآن order_id جاهز للاستخدام
        return response()->json([
            'message' => 'Order created successfully',
            'order_id' => $order->id // هذا هو الرقم الذي كنتِ تبحثين عنه
        ]);
    }
}
