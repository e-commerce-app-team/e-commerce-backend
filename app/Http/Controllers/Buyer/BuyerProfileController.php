<?php
namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BuyerProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'buyer') {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized access. Buyer account required.'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'id'            => $user->id,
                'first_name'          => $user->first_name,
                'last_name'          => $user->last_name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo 
                                    ? asset('storage/' . $user->profile_photo) 
                                    : null,
                'created_at'    => $user->created_at->format('Y-m-d'),
            ]
        ], 200);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'buyer') {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized access. Buyer account required.'
            ], 403);
        }

        $validated = $request->validate([
            'first_name'    => 'sometimes|required|string|max:255',
        'last_name'     => 'sometimes|required|string|max:255',
            'email'         => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'         => 'nullable|string|max:20',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            // حذف الصورة القديمة إن وجدت
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // حفظ الصورة الجديدة
            $path = $request->file('profile_photo')->store('profiles', 'public');
            $validated['profile_photo'] = $path;
        }

        $user->update($validated);
        $user->refresh();

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully',
            'data'    => [
                'id'            => $user->id,
                 'first_name'          => $user->first_name,
                'last_name'          => $user->last_name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo 
                                    ? asset('storage/' . $user->profile_photo) 
                                    : null,
            ]
        ], 200);
    }
}