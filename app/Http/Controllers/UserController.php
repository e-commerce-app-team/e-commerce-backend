<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerRegisterRequest;
use App\Http\Requests\SellerRegisterRequest;
use App\Http\Requests\VendorRegisterRequest;
use App\Http\Requests\WholesaleRegisterRequest;
use App\Models\Ad;
use App\Models\Coupon;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;


class UserController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function login(Request $request)
    {
        // 1. التحقق من صحة البيانات المدخلة
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. تحديد نوع المدخل (إيميل أم هاتف)
        $login = $request->login;
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // 3. محاولة تسجيل الدخول
        $credentials = [$field => $login, 'password' => $request->password];

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // 4. التحقق من حالة الحساب
            if ($user->status !== 'approved') {
                $message = match ($user->status) {
                    'pending' => 'Your account is pending admin approval. Please wait for activation.',
                    'rejected' => 'Your account has been rejected. Please contact support.',
                    default => 'Your account is currently inactive.',
                };
                Auth::logout();
                return response()->json(['success' => false, 'message' => $message], 403);
            }

            // 5. إذا كان الـ 2FA مفعلاً - نرسل OTP ونعيد 202
            if ($user->two_factor_enabled) {
                // إرسال الـ OTP عبر القناة المفضلة
                $this->otpService->sendViaPreferredMethod($user, 'login_2fa');

                // إعادة المستخدم لتجنب بقاء جلسة ويب
                Auth::logout();

                return response()->json([
                    'success' => true,
                    'requires_otp' => true,
                    'message' => 'A verification code has been sent to your ' . $user->two_factor_method . '.',
                    'user_id' => $user->id,
                    'method' => $user->two_factor_method,
                    'masked_to' => $user->two_factor_method === 'email'
                        ? $this->maskEmail($user->email)
                        : $this->maskPhone($user->phone),
                ], 202);
            }

            // 6. تسجيل دخول عادي - توليد التوكن
            $token = $user->createToken('auth_token')->plainTextToken;

            $redirectTo = match ($user->role) {
                'vendor', 'wholesale' => '/seller/home',
                default => '/home',
            };

            $roleMessage = match ($user->role) {
                'vendor' => 'Welcome back, Vendor!',
                'wholesale' => 'Welcome back, Wholesale Vendor!',
                default => 'Welcome back, Buyer!',
            };

            return response()->json([
                'success' => true,
                'requires_otp' => false,
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
                    'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
                    'phone_verified_at' => $user->phone_verified_at?->toDateTimeString(),
                    'profile_photo' => $user->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                    'two_factor_enabled' => $user->two_factor_enabled,
                    'two_factor_method' => $user->two_factor_method,
                ],
            ], 200);
        }

        return response()->json(['success' => false, 'message' => 'Invalid email/phone or password.'], 401);
    }

    // ─── Helper: تقنيع الإيميل والهاتف ───────────────────────────────────
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $masked = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 3)) . substr($local, -1);
        return $masked . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 3) . str_repeat('*', max(0, strlen($phone) - 5)) . substr($phone, -2);
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

        // 2. معالجة الصور
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
        // التحقق مما إذا كان المستخدم قد قام بالتحقق من الـ OTP مسبقاً (عن طريق الإيميل أو الهاتف)
        $isVerified = Cache::pull('verified_registration_' . $request->email) ||
            Cache::pull('verified_registration_' . $request->phone);

        // البريد مؤكد إذا تم التحقق من الـ OTP مسبقاً
        $validated['email_verified_at'] = $isVerified ? now() : null;

        // 4. إنشاء المستخدم
        $user = User::create($validated);

        if (!$isVerified) {
            // 5. إرسال OTP للتحقق من البريد الإلكتروني (الطريقة القديمة)
            $this->otpService->sendViaEmail($user, 'verification');

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please verify your email address.',
                'requires_verification' => true,
                'user_id' => $user->id,
                'email' => $user->email,
            ], 201);
        }

        // إذا كان تم التحقق مسبقاً، نقوم بإصدار التوكن وتسجيل الدخول مباشرة
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'user' => $user,
            'token' => $token,
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
        // التحقق مما إذا كان البائع قد قام بالتحقق من الـ OTP مسبقاً
        $isVerified = Cache::pull('verified_registration_' . $request->email) ||
            Cache::pull('verified_registration_' . $request->phone);

        $validated['email_verified_at'] = $isVerified ? now() : null;

        // 6. الحفظ
        $user = User::create($validated);

        // تعديل: شحن العلاقة الصحيحة الموجودة بالموديل
        $user->load('globalCategory');

        // إذا كان تم التحقق مسبقاً، نقوم بإصدار التوكن وتمريره (بالرغم من أن الحساب قيد المراجعة، يمكنه تسجيل الدخول للوحة التحكم ورؤية رسالة "قيد المراجعة")
        $token = $isVerified ? $user->createToken('auth_token')->plainTextToken : null;

        if (!$isVerified) {
            $this->otpService->sendViaEmail($user, 'verification');
        }

        return response()->json([
            'success' => true,
            'message' => $isVerified ? 'Registration successful. Your seller account is pending admin approval.' : 'Registration successful! Please verify your email address.',
            'requires_verification' => !$isVerified,
            'token' => $token,
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


    // 📌 1. عرض جميع كوبونات التاجر
    // ============================================================
    public function index()
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can view coupons.'
            ], 403);
        }

        $coupons = Coupon::where('seller_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }

    // 📌 2. إنشاء كوبون جديد
    // ============================================================
    public function store(Request $request)
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can create coupons.'
            ], 403);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed,free_shipping',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'required|in:unlimited,once',
            'starts_at' => 'nullable|date|after_or_equal:today',
            'expires_at' => 'nullable|date|after:starts_at',
            'apply_to_all_products' => 'boolean',
            'product_ids' => 'nullable|array|exists:products,id'
        ]);

        // توليد كود فريد
        $code = $this->generateUniqueCode();

        $coupon = Coupon::create([
            'seller_id' => $user->id,
            'code' => $code,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'value' => $request->value,
            'min_order_amount' => $request->min_order_amount,
            'max_uses' => $request->max_uses,
            'usage_limit_per_user' => $request->usage_limit_per_user,
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
            'apply_to_all_products' => $request->apply_to_all_products ?? true,
            'product_ids' => $request->product_ids,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully.',
            'data' => $coupon,
            'coupon_code' => $code
        ], 201);
    }

    // 📌 3. عرض كوبون محدد
    // ============================================================
    public function show($id)
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can view coupons.'
            ], 403);
        }

        $coupon = Coupon::where('seller_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $coupon
        ]);
    }

    // 📌 4. تحديث كوبون
    // ============================================================
    public function update(Request $request, $id)
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can update coupons.'
            ], 403);
        }

        $coupon = Coupon::where('seller_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,fixed,free_shipping',
            'value' => 'sometimes|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'sometimes|in:unlimited,once',
            'starts_at' => 'nullable|date|after_or_equal:today',
            'expires_at' => 'nullable|date|after:starts_at',
            'apply_to_all_products' => 'boolean',
            'product_ids' => 'nullable|array|exists:products,id'
        ]);

        $coupon->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully.',
            'data' => $coupon
        ]);
    }

    // 📌 5. تفعيل/تعطيل كوبون
    // ============================================================
    public function toggle($id)
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can toggle coupons.'
            ], 403);
        }

        $coupon = Coupon::where('seller_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $coupon->is_active = !$coupon->is_active;
        $coupon->save();

        return response()->json([
            'success' => true,
            'message' => $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.'
        ]);
    }

    // 📌 6. حذف كوبون
    // ============================================================
    public function destroy($id)
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can delete coupons.'
            ], 403);
        }

        $coupon = Coupon::where('seller_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully.'
        ]);
    }

    // 📌 7. عرض إحصائيات الكوبون
    // ============================================================
    public function stats($id)
    {
        // 🔥 شرط التحقق من أن المستخدم تاجر
        $user = auth()->user();
        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can view coupon stats.'
            ], 403);
        }

        $coupon = Coupon::where('seller_id', $user->id)
            ->where('id', $id)
            ->with('usages')
            ->firstOrFail();

        $totalDiscount = $coupon->usages->sum('discount_amount');
        $totalOrders = $coupon->usages->count();
        $uniqueUsers = $coupon->usages->groupBy('user_id')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => $coupon,
                'stats' => [
                    'total_uses' => $totalOrders,
                    'total_discount_given' => $totalDiscount,
                    'unique_users' => $uniqueUsers,
                    'remaining_uses' => $coupon->max_uses ? $coupon->max_uses - $coupon->used_count : 'Unlimited'
                ]
            ]
        ]);
    }



    // 📌 8. عرض الكوبونات المتاحة للمشتري
// ============================================================
    public function getAvailableForBuyer()
    {
        $user = auth()->user();

        // 🔥 شرط: المشتري أو التاجر (Vendor/Wholesale)
        if (!in_array($user->role, ['buyer', 'vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // 🔥 جلب الكوبونات المفعلة فقط (is_active = 1) بغض النظر عن apply_to_all_products
        $coupons = Coupon::where('is_active', 1)  // 🔥 فقط المفعلين
            ->with('seller:id,first_name,last_name,store_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }

    // 📌 9. التحقق من صلاحية كوبون (للمشتري)
// ============================================================

    public function validateCoupon(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['buyer', 'vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $request->validate([
            'code' => 'required|string'
        ]);

        // 🔥🔥🔥 جيب السعر من Cache
        $orderTotal = Cache::get('order_total_' . $user->id);
        $productIds = Cache::get('order_product_ids_' . $user->id) ?? [];

        // 🔥 إذا ما في Cache، جيب من الـ Request (للتوافق مع القديم)
        if (!$orderTotal && $request->has('order_total')) {
            $orderTotal = $request->order_total;
            $productIds = $request->product_ids ?? [];
        }

        // 🔥 إذا ما في ولا شي، ارجع خطأ
        if (!$orderTotal) {
            return response()->json([
                'success' => false,
                'message' => 'No order total found. Please create an order first or provide order_total.'
            ], 400);
        }

        $code = strtoupper(trim($request->code));
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code.'
            ], 404);
        }

        // 🔥 تحقق: إذا الكوبون خاص بمنتجات وما في منتجات محددة
        if (!$coupon->apply_to_all_products && empty($productIds)) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon applies to specific products. Please add products to cart first.',
                'coupon' => [
                    'code' => $coupon->code,
                    'title' => $coupon->title,
                    'product_ids' => $coupon->product_ids
                ]
            ], 400);
        }

        // التحقق من الصلاحية
        $validation = $coupon->isValid(
            $user->id,
            $orderTotal,
            $productIds
        );

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['message']
            ], 400);
        }

        $discountAmount = $coupon->calculateDiscount($orderTotal);

        return response()->json([
            'success' => true,
            'coupon' => $coupon,
            'total_before_discount' => round($orderTotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'final_total' => round($orderTotal - $discountAmount, 2)
        ]);
    }
    // 📌 1. عرض جميع إعلانات التاجر مع فلترة
    // ============================================================
    public function indexAd(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can manage ads.'
            ], 403);
        }

        $query = Ad::forSeller($user->id);

        // فلترة حسب الحالة
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'pending') {
                $query->pending();
            } elseif ($status === 'expired') {
                $query->expired();
            } elseif ($status === 'rejected') {
                $query->where('status', 'rejected');
            }
        }

        // فلترة حسب النوع
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $ads = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $ads,
            'filters' => [
                'status' => $request->status,
                'type' => $request->type,
            ]
        ]);
    }

    // 📌 2. الحصول على أنواع الإعلانات المتاحة مع الأسعار
    // ============================================================
    public function getAdTypes()
    {
        return response()->json([
            'success' => true,
            'balance' => auth()->user()->balance ?? 0,
            'types' => [
                [
                    'type' => 'banner',
                    'label' => 'بانر رئيسي',
                    'icon' => '📢',
                    'description' => 'ظهور في أعلى الشاشة الرئيسية للمشترين',
                    'location' => 'الشاشة الرئيسية',
                    'price_per_day' => 2000,
                    'prices' => [
                        '1_day' => 2000,
                        '3_days' => 8000,
                        '1_week' => 15000,
                        '1_month' => 50000,
                    ]
                ],
                [
                    'type' => 'promoted_product',
                    'label' => 'منتج معزز',
                    'icon' => '⭐',
                    'description' => 'منتجك يظهر أول نتائج البحث والاستكشاف',
                    'location' => 'نتائج البحث والاستكشاف',
                    'price_per_day' => 3000,
                    'prices' => [
                        '1_day' => 3000,
                        '3_days' => 8000,
                        '1_week' => 15000,
                        '1_month' => 50000,
                    ]
                ],
                [
                    'type' => 'featured_store',
                    'label' => 'متجر مميز',
                    'icon' => '🏪',
                    'description' => 'متجرك يظهر في قسم "متاجر مميزة" للمشترين',
                    'location' => 'قسم المتاجر المميزة',
                    'price_per_day' => 4000,
                    'prices' => [
                        '1_day' => 4000,
                        '3_days' => 10000,
                        '1_week' => 20000,
                        '1_month' => 60000,
                    ]
                ],
                [
                    'type' => 'paid_notification',
                    'label' => 'إشعار مدفوع',
                    'icon' => '🔔',
                    'description' => 'إشعار يصل لجميع مستخدمي التطبيق مباشرة',
                    'location' => 'إشعارات التطبيق',
                    'price_per_day' => 15000,
                    'prices' => [
                        '1_day' => 15000,
                        '3_days' => 40000,
                        '1_week' => 80000,
                        '1_month' => 250000,
                    ]
                ],
            ]
        ]);
    }

    // 📌 3. إنشاء طلب إعلان جديد
    // ============================================================
    public function storeAd(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only vendors can create ads.'
            ], 403);
        }

        $request->validate([
            'type' => 'required|in:banner,promoted_product,featured_store,paid_notification',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
            'link' => 'nullable|string',
            'duration' => 'required|in:1_day,3_days,1_week,1_month',
        ]);

        // حساب السعر حسب المدة
        $prices = [
            '1_day' => $this->getPriceByType($request->type, '1_day'),
            '3_days' => $this->getPriceByType($request->type, '3_days'),
            '1_week' => $this->getPriceByType($request->type, '1_week'),
            '1_month' => $this->getPriceByType($request->type, '1_month'),
        ];

        $price = $prices[$request->duration];

        // التحقق من رصيد المحفظة
        if ($user->balance < $price) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance.'
            ], 402); // 402 Payment Required
        }

        // حساب تواريخ البداية والنهاية
        $startsAt = now();
        $expiresAt = $this->calculateExpiryDate($request->duration);

        // رفع الصورة
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ads/images', 'public');
        }

        $ad = Ad::create([
            'seller_id' => $user->id,
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'image_url' => $imagePath,
            'link' => $request->link,
            'duration' => $request->duration,
            'price' => $price,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => 'pending', // يبدأ قيد المراجعة
        ]);

        // ✅ بدلاً من السطرين السابقين
        $user->decrement('balance', $price);

        return response()->json([
            'success' => true,
            'message' => 'Ad request submitted successfully. Waiting for admin approval.',
            'data' => $ad,
            'price_details' => [
                'duration' => $ad->getDurationLabel(),
                'price' => $price,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]
        ], 201);
    }

    // 📌 4. عرض إعلان محدد
    // ============================================================
    public function showAd($id)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $ad = Ad::forSeller($user->id)->with('views')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ad,
            'stats' => [
                'views_count' => $ad->views_count,
                'clicks_count' => $ad->clicks_count,
                'daily_views' => $this->getDailyViews($ad),
            ]
        ]);
    }

    // 📌 5. تحديث إعلان (قبل الموافقة)
    // ============================================================
    public function updateAd(Request $request, $id)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $ad = Ad::forSeller($user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
            'link' => 'nullable|url',
            'duration' => 'sometimes|in:1_day,3_days,1_week,1_month',
        ]);

        $data = $request->except(['image']);

        // رفع الصورة الجديدة
        if ($request->hasFile('image')) {
            if ($ad->image_url) {
                Storage::disk('public')->delete($ad->image_url);
            }
            $data['image_url'] = $request->file('image')->store('ads/images', 'public');
        }

        // تحديث السعر إذا تغيرت المدة
        if ($request->has('duration')) {
            $prices = [
                '1_day' => $this->getPriceByType($ad->type, '1_day'),
                '3_days' => $this->getPriceByType($ad->type, '3_days'),
                '1_week' => $this->getPriceByType($ad->type, '1_week'),
                '1_month' => $this->getPriceByType($ad->type, '1_month'),
            ];
            $data['price'] = $prices[$request->duration];
            $data['expires_at'] = $this->calculateExpiryDate($request->duration);
        }

        $ad->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Ad updated successfully.',
            'data' => $ad
        ]);
    }

    // 📌 6. إلغاء طلب الإعلان (حذف)
    // ============================================================
    public function destroyAd($id)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $ad = Ad::forSeller($user->id)
            ->whereIn('status', ['pending', 'rejected'])
            ->findOrFail($id);

        if ($ad->image_url) {
            Storage::disk('public')->delete($ad->image_url);
        }

        $ad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ad deleted successfully.'
        ]);
    }

    // 📌 7. عرض إحصائيات الإعلانات (Dashboard)
    // ============================================================
    public function dashboard()
    {
        $user = auth()->user();

        if (!in_array($user->role, ['vendor', 'wholesale'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        // 🔥 استعلام أساسي
        $baseQuery = Ad::forSeller($user->id);

        return response()->json([
            'success' => true,
            'stats' => [
                'total_ads' => (clone $baseQuery)->count(),
                'active_ads' => (clone $baseQuery)->active()->count(),
                'pending_ads' => (clone $baseQuery)->pending()->count(),
                'expired_ads' => (clone $baseQuery)->expired()->count(),
                'rejected_ads' => (clone $baseQuery)->where('status', 'rejected')->count(),
                'total_views' => (clone $baseQuery)->sum('views_count'),
                'total_clicks' => (clone $baseQuery)->sum('clicks_count'),
                'total_spent' => (clone $baseQuery)->sum('price'),
            ],
            'by_type' => [
                'banner' => (clone $baseQuery)->where('type', 'banner')->count(),
                'promoted_product' => (clone $baseQuery)->where('type', 'promoted_product')->count(),
                'featured_store' => (clone $baseQuery)->where('type', 'featured_store')->count(),
                'paid_notification' => (clone $baseQuery)->where('type', 'paid_notification')->count(),
            ]
        ]);
    }
    // 📌 دوال مساعدة
    // ============================================================

    private function getPriceByType($type, $duration)
    {
        $prices = [
            'banner' => [
                '1_day' => 2000,
                '3_days' => 8000,
                '1_week' => 15000,
                '1_month' => 50000,
            ],
            'promoted_product' => [
                '1_day' => 3000,
                '3_days' => 8000,
                '1_week' => 15000,
                '1_month' => 50000,
            ],
            'featured_store' => [
                '1_day' => 4000,
                '3_days' => 10000,
                '1_week' => 20000,
                '1_month' => 60000,
            ],
            'paid_notification' => [
                '1_day' => 15000,
                '3_days' => 40000,
                '1_week' => 80000,
                '1_month' => 250000,
            ],
        ];

        return $prices[$type][$duration] ?? 0;
    }

    private function calculateExpiryDate($duration)
    {
        $map = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '1_month' => now()->addMonth(),
        ];

        return $map[$duration] ?? now()->addDay();
    }

    private function getDailyViews($ad)
    {
        return $ad->views()
            ->where('type', 'view')
            ->whereDate('created_at', today())
            ->count();
    }

    // 📌 وظيفة مساعدة: توليد كود فريد للكوبون
// ============================================================
    private function generateUniqueCode()
    {
        $prefix = 'CPN-';
        $code = $prefix . strtoupper(Str::random(8));

        while (Coupon::where('code', $code)->exists()) {
            $code = $prefix . strtoupper(Str::random(8));
        }

        return $code;
    }
}

