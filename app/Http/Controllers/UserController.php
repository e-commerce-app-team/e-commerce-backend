<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerRegisterRequest;
use App\Http\Requests\VendorRegisterRequest;
use App\Http\Requests\WholesaleRegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function login(Request $request)
    {
        // 1. التحقق من صحة البيانات المدخلة
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. محاولة تسجيل الدخول
        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // 3. التحقق من حالة الحساب (Status Check)
            if ($user->status !== 'approved') {

                // تحديد رسالة الخطأ بناءً على الحالة
                $message = '';
                if ($user->status === 'pending') {
                    $message = 'Your account is pending admin approval. Please wait for activation.';
                } elseif ($user->status === 'rejected') {
                    $message = 'Your account has been rejected. Please contact support.';
                } else {
                    $message = 'Your account is currently inactive.';
                }

                // تسجيل الخروج لعدم السماح ببقاء الجلسة
                Auth::logout();

                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 403); // 403 Forbidden
            }

            // 4. توليد التوكن (فقط في حال كان الحساب مقبولاً)
            $token = $user->createToken('auth_token')->plainTextToken;

            // 5. التوجيه بناءً على الرتبة (User Role)
            $redirectTo = '';
            $roleMessage = '';

            switch ($user->role) {
                case 'vendor':
                    $redirectTo = '/vendor/home';
                    $roleMessage = 'Welcome back, Vendor!';
                    break;
                case 'wholesale':
                    $redirectTo = '/wholesale/home';
                    $roleMessage = 'Welcome back, Wholesale Vendor!';
                    break;
                default:
                    $redirectTo = '/home';
                    $roleMessage = 'Welcome back, Buyer!';
                    break;
            }

            // 6. إرسال الرد النهائي مع البيانات والتوكن
            return response()->json([
                'success' => true,
                'message' => $roleMessage,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'redirect_to' => $redirectTo,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_photo' => $user->profile_photo,
                ]
            ], 200);
        }

        // 7. في حال كانت بيانات الدخول خاطئة
        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password'
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'logout success',

        ]);
    }

    public function registerBuyer(BuyerRegisterRequest $request)
    {
        // 1. جلب البيانات
        $validated = $request->validated();

        // 2. معالجة صورة الملف الشخصي
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('buyers/profiles', 'public');
        }

        // 3. إضافة معالجة صورة الهوية (السطر المطلوب)
        if ($request->hasFile('id_card_photo')) {
            $validated['id_card_photo'] = $request->file('id_card_photo')->store('buyers/ids', 'public');
        }

        // 4. تشفير البيانات الأساسية
        $validated['password'] = Hash::make($request->password);
        $validated['role'] = 'buyer';

        // 5. إنشاء المستخدم
        $user = User::create($validated);

        return response()->json([
            'message' => 'Registration successful. Your account is pending admin approval. Please verify your phone via OTP..',
            'user' => $user
        ], 201);
    }
    public function registerVendor(VendorRegisterRequest $request)
    {
        // 1. جلب البيانات المفحوصة
        $validated = $request->validated();

        // 2. معالجة الصور
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('vendors/profiles', 'public');
        }

        // صورة الهوية إجبارية للبائع
        $validated['id_card_photo'] = $request->file('id_card_photo')->store('vendors/ids', 'public');

        // 3. التشفير والحقول الإضافية
        $validated['password'] = Hash::make($request->password);
        $validated['wallet_pin'] = Hash::make($request->wallet_pin);
        $validated['role'] = 'vendor';

        // 4. الحفظ في قاعدة البيانات
        $user = User::create($validated);

        return response()->json([
            'message' => 'Registration successful. Your account is pending admin approval. Please verify your phone via OTP..',
            'user' => $user
        ], 201);
    }

    public function registerWholesale(WholesaleRegisterRequest $request)
    {
        $validated = $request->validated();

        // صورة الملف الشخصي
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('wholesale/profiles', 'public');
        }

        // صورة السجل التجاري
        $validated['commercial_record_photo'] = $request->file('commercial_record_photo')->store('wholesale/records', 'public');

        // صورة الهوية الشخصية (السطر المطلوب)
        $validated['id_card_photo'] = $request->file('id_card_photo')->store('wholesale/ids', 'public');

        // التشفير والحقول الثابتة
        $validated['password'] = Hash::make($request->password);
        $validated['wallet_pin'] = Hash::make($request->wallet_pin);
        $validated['role'] = 'wholesale';

        $user = User::create($validated);

        return response()->json([
            'message' => 'Registration successful. Your account is pending admin approval. Please verify your phone via OTP..',
            'user' => $user
        ], 201);
    }
}
