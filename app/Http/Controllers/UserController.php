<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerRegisterRequest;
use App\Http\Requests\SellerRegisterRequest;
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
        // 1. جلب البيانات المفحوصة
        $validated = $request->validated();

        // 2. معالجة صورة الملف الشخصي (اختيارية)
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('buyers/profiles', 'public');
        }

        // 3. معالجة صورة الهوية (اختيارية)
        if ($request->hasFile('id_card_photo')) {
            $validated['id_card_photo'] = $request->file('id_card_photo')->store('buyers/ids', 'public');
        }

        // 4. تشفير كلمة المرور وتحديد الرتبة والحالة
        $validated['password'] = Hash::make($request->password);
        $validated['role'] = 'buyer';

        // الحالة 'approved' لكي يتمكن من تسجيل الدخول فوراً
        $validated['status'] = 'approved';

        // 5. إنشاء المستخدم
        $user = User::create($validated);

        // 6. إرجاع الرد بدون توكن
        return response()->json([
            'success' => true,
            'message' => 'Buyer registered successfully. Please log in.',
            'user' => $user
        ], 201);
    }

    public function registerSeller(SellerRegisterRequest $request)
    {
        // 1. جلب البيانات التي تم التحقق منها
        $validated = $request->validated();

        // 2. تصفية البيانات بناءً على الرتبة (Role)
        // إذا كان البائع عادي (vendor)، نقوم بحذف بيانات الجملة من مصفوفة الـ validated
        if ($request->role === 'vendor') {
            unset($validated['commercial_record_photo']);
            unset($validated['tax_number']); // تأكد من حذف الرقم الضريبي أيضاً
        }

        // 3. معالجة الصور (رفع الصور العامة)
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('sellers/profiles', 'public');
        }
        if ($request->hasFile('store_logo')) {
            $validated['store_logo'] = $request->file('store_logo')->store('sellers/logos', 'public');
        }

        // الهوية إجبارية للبائع
        $validated['id_card_photo'] = $request->file('id_card_photo')->store('sellers/ids', 'public');

        // 4. معالجة السجل التجاري فقط لبائع الجملة
        if ($request->role === 'wholesale' && $request->hasFile('commercial_record_photo')) {
            $validated['commercial_record_photo'] = $request->file('commercial_record_photo')->store('sellers/records', 'public');
        }

        // 5. التشفير والحالة
        $validated['password'] = Hash::make($request->password);
        $validated['status'] = 'pending';

        // 6. الحفظ
        $user = User::create($validated);

        $user->load('categoryRel');
        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your seller account is pending admin approval.',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'store_name' => $user->store_name,
                'category' => $user->categoryRel ? $user->categoryRel->name : 'N/A',
                'status' => $user->status,

                // --- الحقول الإضافية التي طلبتها ---
                'commercial_registration_number' => $user->commercial_registration_number,
                // نستخدم Storage::url للحصول على الرابط الكامل للصورة بدلاً من المسار الداخلي فقط
                'commercial_record_photo' => $user->commercial_record_photo ? asset('storage/' . $user->commercial_record_photo) : null,
                'tax_number' => $user->tax_number,
                // ----------------------------------

                'created_at' => $user->created_at,
            ]
        ], 201);
    }

}
