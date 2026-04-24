<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Hash;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{


    public function login(Request $request)
    {
        // 1. التحقق من المدخلات (استخدام email بدل phone)
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // 2. البحث عن الأدمن بواسطة الإيميل
        $admin = Admin::where('email', $request->email)->first();

        // 3. التحقق من وجود الحساب وصحة كلمة المرور
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // 4. توليد التوكن
        $token = $admin->createToken('admin-token')->plainTextToken;

        // 5. الرد (تعديل اسم حقل الصورة إلى profile_photo)
        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email, // أضفت لكِ الإيميل هنا أيضاً بدل الفون
                'profile_photo' => $admin->profile_photo, // التعديل المطلوب
            ],
        ]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
