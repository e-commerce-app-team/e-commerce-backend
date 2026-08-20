<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StoreFollowController extends Controller
{

    // متابعة متجر

    public function follow(Request $request, $id)
    {
        $user = $request->user();

        // التأكد من وجود المتجر/البائع
        $seller = User::find($id);
        if (!$seller) {
            return response()->json([
                'status'  => false,
                'message' => 'Store not found'
            ], 404);
        }

        // منع المشتري من متابعة نفسه إن كان حاسبه بائعاً بالخطأ
        if ($user->id == $seller->id) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot follow yourself'
            ], 400);
        }

        // إتاحة المتابعة دون تكرار
        $user->followingStores()->syncWithoutDetaching([$seller->id => ['followed_at' => now()]]);

        return response()->json([
            'status'  => true,
            'message' => 'Store followed successfully'
        ], 200);
    }


    // إلغاء متابعة متجر

public function unfollow(Request $request, $id)
{
    $user = $request->user();

    // 1. تحديد اسم الجدول بوضوح لمنع الالتباس في SQL
    $isFollowing = $user->followingStores()
                        ->where('store_follows.seller_id', $id)
                        ->exists();

    if (!$isFollowing) {
        return response()->json([
            'status'  => false,
            'message' => 'You are not following this store.'
        ], 400);
    }

    // 2. إزالة المتابعة
    $user->followingStores()->detach($id);

    return response()->json([
        'status'  => true,
        'message' => 'Store unfollowed successfully'
    ], 200);
}
    // عرض المتاجر التي يتابعها المشتري

    public function followingStores(Request $request)
    {
        $user = $request->user();

        $stores = $user->followingStores()->get()->map(function ($store) {
            return [
                'id'            => $store->id,
                'store_name'          => $store->store_name,
                'profile_photo' => $store->profile_photo
                    ? asset('storage/' . $store->profile_photo)
                    : null,
                'followed_at'   => $store->pivot->followed_at,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $stores
        ], 200);
    }
}
