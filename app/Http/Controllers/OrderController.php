<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function store(Request $request)
    {
        $buyer = auth()->user();

        $request->validate([
            // تعديل التحقق ليتأكد أن المعرف يخص بائعاً أو تاجر جملة فقط
            'seller_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereIn('role', ['vendor', 'wholesale']);
                }),
            ],
            'total_price' => 'required|numeric|min:1'
        ]);

        // 3. إنشاء الطلب
        $order = Order::create([
            'user_id' => $buyer->id,
            'seller_id' => $request->seller_id,
            'total_price' => $request->total_price,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'order_id' => $order->id
        ], 201);
    }
}
