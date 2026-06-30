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
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function login(Request $request)
    {
        // 1. التحقق من صحة البيانات المدخلة
        $request->validate([
            'login' => 'required|string', // login = email OR phone
            'password' => 'required|string',
        ]);

        // 2. تحديد نوع المدخل (إيميل أم موبايل)
        $login = $request->login;
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // 3. محاولة تسجيل الدخول
        $credentials = [
            $field => $login,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // 4. التحقق من حالة الحساب (Status Check)
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
                ], 403);
            }

            // 5. التوجيه وتوليد التوكن بناءً على الرتبة (User Role)
            $redirectTo = '';
            $roleMessage = '';
            $token = null;

            switch ($user->role) {
                case 'vendor':
                    $redirectTo = '/vendor/home';
                    $roleMessage = 'Welcome back, Vendor!';
                    $token = $user->createToken('auth_token')->plainTextToken;
                    break;

                case 'wholesale':
                    $redirectTo = '/wholesale/home';
                    $roleMessage = 'Welcome back, Wholesale Vendor!';
                    $token = $user->createToken('auth_token')->plainTextToken;
                    break;

                default:
                    $redirectTo = '/home';
                    $roleMessage = 'Welcome back, Buyer!';
                    $token = null;
                    break;
            }

            // 6. إرسال الرد النهائي مع جميع الحقول
            return response()->json([
                'success' => true,
                'message' => $roleMessage,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'redirect_to' => $redirectTo,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'store_name' => $user->store_name ?? null,
                    'category' => $user->category ?? null,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toDateTimeString() : null,
                    'phone_verified_at' => $user->phone_verified_at ? $user->phone_verified_at->toDateTimeString() : null,
                    'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                ]
            ], 200);
        }

        // في حال فشل بيانات تسجيل الدخول
        return response()->json([
            'success' => false,
            'message' => 'Invalid email/phone or password.'
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
        // 6. الحفظ
        $user = User::create($validated);

        // تعديل: شحن العلاقة الصحيحة الموجودة بالموديل
        $user->load('globalCategory');

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

                // تعديل: جلب البيانات من العلاقة الصحيحة globalCategory
                'category_id' => $user->globalCategory ? $user->globalCategory->id : null,
                'category' => $user->globalCategory ? $user->globalCategory->name : 'N/A',

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

    //  [تابع 1]: إدخال وحفظ المعلومات لأول مرة (CREATE)
    public function createStoreSettings(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'store_description' => 'nullable|string|max:1000',
            'store_email' => 'nullable|email|max:255',
            'detailed_address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'return_policy' => 'nullable|string|max:2000',
            'profile_photo' => 'nullable|image|max:2048',
            'store_logo' => 'nullable|image|max:2048',
            'store_cover_photo' => 'nullable|image|max:3072',
            'working_hours' => 'nullable|array',
            'social_links' => 'nullable|array',
        ]);

        // رفع الصور لأول مرة دون الحاجة للحذف
        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('sellers/profiles', 'public');
        }
        if ($request->hasFile('store_logo')) {
            $validated['store_logo'] = $request->file('store_logo')->store('sellers/logos', 'public');
        }
        if ($request->hasFile('store_cover_photo')) {
            $validated['store_cover_photo'] = $request->file('store_cover_photo')->store('sellers/covers', 'public');
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store settings created successfully.',
            'user' => $user
        ], 201);
    }

    // 🛠️ [تابع 2]: تعديل وتحديث المعلومات (UPDATE)
    public function updateStoreSettings(Request $request)
    {
        $user = auth()->user();

        // التعديل غالباً تكون فيه الحقول اختيارية (sometimes) لأن البائع قد يغير حقل واحد فقط
        $validated = $request->validate([
            'store_name' => 'sometimes|required|string|max:255',
            'store_description' => 'nullable|string|max:1000',
            'store_email' => 'nullable|email|max:255',
            'detailed_address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'return_policy' => 'nullable|string|max:2000',
            'profile_photo' => 'nullable|image|max:2048',
            'store_logo' => 'nullable|image|max:2048',
            'store_cover_photo' => 'nullable|image|max:3072',
            'working_hours' => 'nullable|array',
            'social_links' => 'nullable|array',
        ]);

        // صيانة الصور عند التعديل (حذف القديم ورفع الجديد)
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $validated['profile_photo'] = $request->file('profile_photo')->store('sellers/profiles', 'public');
        }

        if ($request->hasFile('store_logo')) {
            if ($user->store_logo && Storage::disk('public')->exists($user->store_logo)) {
                Storage::disk('public')->delete($user->store_logo);
            }
            $validated['store_logo'] = $request->file('store_logo')->store('sellers/logos', 'public');
        }

        if ($request->hasFile('store_cover_photo')) {
            if ($user->store_cover_photo && Storage::disk('public')->exists($user->store_cover_photo)) {
                Storage::disk('public')->delete($user->store_cover_photo);
            }
            $validated['store_cover_photo'] = $request->file('store_cover_photo')->store('sellers/covers', 'public');
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store settings updated successfully.',
            'user' => $user
        ], 200);
    }

    // 🔄 [تابع 3]: استرجاع وجلب المعلومات للعرض (GET / READ)
    public function getStoreSettings()
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                'store_name' => $user->store_name,
                'store_description' => $user->store_description,
                'store_logo' => $user->store_logo ? asset('storage/' . $user->store_logo) : null,
                'store_cover_photo' => $user->store_cover_photo ? asset('storage/' . $user->store_cover_photo) : null,
                'working_hours' => $user->working_hours,
                'return_policy' => $user->return_policy,
                'store_email' => $user->store_email ?? $user->email,
                'social_links' => $user->social_links,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'detailed_address' => $user->detailed_address,
            ]
        ], 200);
    }
}

