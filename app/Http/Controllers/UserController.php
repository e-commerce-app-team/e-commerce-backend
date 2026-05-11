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

            // 4. التوجيه وتوليد التوكن بناءً على الرتبة (User Role)
            $redirectTo = '';
            $roleMessage = '';
            $token = null; // القيمة الافتراضية للتوكن

            switch ($user->role) {
                case 'vendor':
                    $redirectTo = '/vendor/home';
                    $roleMessage = 'Welcome back, Vendor!';
                    // توليد توكن فقط للبائع
                    $token = $user->createToken('auth_token')->plainTextToken;
                    break;

                case 'wholesale':
                    $redirectTo = '/wholesale/home';
                    $roleMessage = 'Welcome back, Wholesale Vendor!';
                    // توليد توكن فقط للبائع بالجملة
                    $token = $user->createToken('auth_token')->plainTextToken;
                    break;

                default:
                    $redirectTo = '/home';
                    $roleMessage = 'Welcome back, Buyer!';
                    // المشتري لا يتم توليد توكن له هنا (لأنه استلمه عند الـ register)
                    $token = null;
                    break;
            }

            // 5. إرسال الرد النهائي
            return response()->json([
                'success' => true,
                'message' => $roleMessage,
                'access_token' => $token, // سيكون null في حال كان المشتري هو من سجل دخوله
                'token_type' => 'Bearer',
                'redirect_to' => $redirectTo,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                ]
            ], 200);
        }

        // في حال فشل بيانات تسجيل الدخول
        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.'
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

        // 2. معالجة الصور وإضافتها لنفس المصفوفة ($validated)
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('buyers/profiles', 'public');
        }

        if ($request->hasFile('id_card_photo')) {
            $validated['id_card_photo'] = $request->file('id_card_photo')->store('buyers/ids', 'public');
        }

        // 3. تشفير كلمة المرور وتحديد الرتبة والحالة
        $validated['password'] = Hash::make($request->password);
        $validated['role'] = 'buyer';
        $validated['status'] = 'approved';

        // 4. إنشاء المستخدم باستخدام المصفوفة التي تحتوي الآن على الصور
        $user = User::create($validated);

        // --- إضافة: توليد التوكن للمشتري عند التسجيل ---
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. الرد النهائي مع التوكن
        return response()->json([
            'success' => true,
            'message' => 'Buyer registered successfully.',
            'access_token' => $token, // التوكن المضاف
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                'id_card_photo' => $user->id_card_photo ? asset('storage/' . $user->id_card_photo) : null,
            ]
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
                'category_id' => $user->categoryRel->id,
                'category' => $user->categoryRel ? $user->categoryRel->name : 'N/A',
                'status' => $user->status,

                'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                'store_logo' => $user->store_logo ? asset('storage/' . $user->store_logo) : null,
                'id_card_photo' => $user->id_card_photo ? asset('storage/' . $user->id_card_photo) : null,

                'commercial_registration_number' => $user->commercial_registration_number,
                'commercial_record_photo' => $user->commercial_record_photo ? asset('storage/' . $user->commercial_record_photo) : null,
                'tax_number' => $user->tax_number,

                'created_at' => $user->created_at,
            ]
        ], 201);
    }

}