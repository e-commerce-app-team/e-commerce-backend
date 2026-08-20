<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReviewController extends Controller
{

    // Add a new product review (requires prior purchase)

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        // 1. Verify that the buyer has actually purchased the product and the order is placed/completed
        $hasPurchased = Order::where('user_id', $user->id)
            ->whereHas('products', function ($query) use ($productId) {
                $query->where('product_id', $productId); // أو $query->where('products.id', $productId);
            })
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'status'  => false,
                'message' => 'Sorry, you can only review products you have purchased.'
            ], 403);
        }

        // 2. Check if a review already exists for this product by the same user
        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'status'  => false,
                'message' => 'You have already submitted a review for this product.'
            ], 422);
        }

        // 3. Create the review
        $review = Review::create([
            'user_id'    => $user->id,
            'product_id' => $productId,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Review submitted successfully.',
            'data'    => $review
        ], 201);
    }

    //Retrieve all reviews submitted by the current buyer

    public function index(Request $request)
    {
        $reviews = $request->user()->reviews()
            ->with('product:id,name,images')
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $reviews
        ], 200);
    }


    // Update a review within 24 hours of creation

    public function update(Request $request, $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::where('user_id', $request->user()->id)->findOrFail($id);

        // Verify time constraint: allow update only within 24 hours
        if ($review->created_at->addHours(24)->isPast()) {
            return response()->json([
                'status'  => false,
                'message' => 'Review edit window has expired. Updates are allowed only within 24 hours of creation.'
            ], 422);
        }

        $review->update([
            'rating'  => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Review updated successfully.',
            'data'    => $review
        ], 200);
    }
}
