<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Hash;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{

    public function login(Request $request)
    {
        // 1. Validation with English messages
        $request->validate([
            'phone' => ['required', 'regex:/^09[0-9]{8}$/'],
            'password' => 'required|string',
        ]);

        // 2. Find admin by phone
        $admin = Admin::where('phone', $request->phone)->first();

        // 3. Check credentials
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Invalid phone number or password.'], 401);
        }

        // 4. Create Token
        $token = $admin->createToken('admin-token')->plainTextToken;

        // 5. Response (Simplified without role_display)
        return response()->json([
            'token' => $token,
            'role' => $admin->role, // This will return values like: 'super_admin', 'products_admin', etc.
            'admin' => [
                'id' => $admin->id,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'phone' => $admin->phone,
                'profile_photo' => $admin->profile_photo,
                'created_at' => $admin->created_at
            ],
        ]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}

