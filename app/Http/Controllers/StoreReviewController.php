<?php

namespace App\Http\Controllers;

use App\Models\StoreReview;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreReviewController extends Controller
{
    
     // Rate a store (Buyers only)
    public function store(Request $request, $storeId): JsonResponse
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $currentUser = auth()->user();

        // التحقق من أن صاحب الحساب مشتري فقط (Buyer)
        if ($currentUser->role !== 'buyer') {
            return response()->json([
                'status'  => false,
                'message' => 'Only buyers are allowed to review stores.'
            ], 403);
        }

        // التحقق من أن المتجر المراد تقييمه موجود وهو بائع بالفعل
        $store = User::whereIn('role', ['vendor', 'wholesale'])->find($storeId);
        if (!$store) {
            return response()->json([
                'status'  => false,
                'message' => 'Store not found.'
            ], 404);
        }

        // شرط أمان إضافي لمنع أي تقييم ذاتي
        if ($currentUser->id == $storeId) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot review your own store.'
            ], 403);
        }

        // 4. حفظ التقييم أو تحديثه إذا كان المشتري قد قيّم المتجر سابقاً
        $review = StoreReview::updateOrCreate(
            [
                'user_id'  => $currentUser->id,
                'store_id' => $storeId,
            ],
            [
                'rating'  => $request->rating,
                'comment' => $request->comment,
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Store review submitted successfully.',
            'data'    => $review
        ], 201);
    }
}