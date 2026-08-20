<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // ✅ تأكد من وجود هذا السطر

class IsOrdersAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ سجل الـ Route الحالي
        Log::info('=== IsOrdersAdmin Middleware START ===');
        Log::info('Route URI: ' . $request->route()->uri());
        Log::info('Route Parameters: ', $request->route()->parameters());
        Log::info('Request Method: ' . $request->method());
        Log::info('User ID: ' . (auth()->id() ?? 'guest'));

        // ✅ 1. التحقق من صلاحيات المستخدم
        $admin = auth()->user();
        if (!$admin || ($admin->role !== 'orders_admin' && $admin->role !== 'super_admin')) {
            Log::warning('Access Denied: User role is ' . ($admin ? $admin->role : 'guest'));
            return response()->json(['message' => 'Access Denied. Orders Admin only.'], 403);
        }

        Log::info('User authorized: ' . $admin->email . ' (Role: ' . $admin->role . ')');

        // ✅ 2. التحقق من وجود 'id' في الـ Route
        if ($request->route('id')) {
            Log::info('Looking for Order with ID: ' . $request->route('id'));
            try {
                $order = Order::findOrFail($request->route('id'));
                Log::info('Order found: ID ' . $order->id . ' | Status: ' . $order->status);
            } catch (\Exception $e) {
                Log::error('Order not found: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }
        } else {
            Log::info('No ID in route, skipping order check');
        }

        Log::info('=== IsOrdersAdmin Middleware END ===');
        return $next($request);
    }
}