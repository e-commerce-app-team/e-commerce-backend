<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    // 0. PRE-REGISTRATION OTP (قبل إنشاء الحساب)
    // ─────────────────────────────────────────────────────────────────────────
    public function sendRegistrationOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:phone|email|unique:users,email',
            'phone' => 'required_without:email|string|unique:users,phone',
            'first_name' => 'required|string',
            'method' => 'required|in:email,phone',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // ✅ التصحيح: استخدام input('method') بدلاً من method
        if ($request->input('method') === 'email') {
            $sent = $this->otpService->sendRegistrationOtpViaEmail($request->email, $request->first_name);
            $identifier = $request->email;
        } else {
            $sent = $this->otpService->sendRegistrationOtpViaPhone($request->phone);
            $identifier = $request->phone;
        }

        if (!$sent) {
            return response()->json(['success' => false, 'message' => 'Failed to send OTP. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "OTP sent successfully to your {$request->input('method')}.",
            'method' => $request->input('method'),
        ], 200);
    }
    public function verifyRegistrationOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string', // يمكن أن يكون الإيميل أو رقم الهاتف
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (!$this->otpService->verifyRegistrationOtp($request->identifier, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please request a new one.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully. You can now proceed with registration.',
        ], 200);
    }

    // 1. FORGOT PASSWORD: إرسال رمز OTP لاستعادة كلمة المرور
    // ─────────────────────────────────────────────────────────────────────────
    public function sendForgotPasswordOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // يقبل إيميل أو رقم هاتف
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $login = $request->login;
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $login)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => $field === 'email'
                    ? 'No account found with this email address.'
                    : 'No account found with this phone number.',
            ], 404);
        }

        // إرسال OTP عبر القناة المناسبة
        $sent = $field === 'email'
            ? $this->otpService->sendViaEmail($user, 'reset')
            : $this->otpService->sendViaPhone($user, 'reset');

        if (!$sent) {
            return response()->json(['success' => false, 'message' => 'Failed to send OTP. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "OTP sent successfully to your {$field}.",
            'method' => $field,
            // نرسل النص بشكل مُقنَّع لحماية الخصوصية
            'masked_to' => $this->maskContact($login, $field),
        ], 200);
    }

    // 2. FORGOT PASSWORD: التحقق من رمز OTP
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyForgotPasswordOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $request->login)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        if (!$this->otpService->verify($user, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code. Please request a new one.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully. You can now reset your password.',
            'login' => $request->login,
        ], 200);
    }

    // 3. FORGOT PASSWORD: إعادة تعيين كلمة المرور
    // ─────────────────────────────────────────────────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'otp' => 'required|string|size:6',
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'],
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $request->login)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        // التحقق مرة أخرى من الرمز لضمان الأمان
        if (!$this->otpService->verify($user, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please request a new one.',
            ], 422);
        }

        // تحديث كلمة المرور ومسح الـ OTP
        $user->update(['password' => Hash::make($request->password)]);
        $user->clearOtp();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ], 200);
    }

    // 4. 2FA LOGIN: التحقق من الـ OTP لإتمام تسجيل الدخول
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);

        if (!$this->otpService->verify($user, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        // مسح الـ OTP بعد التحقق الناجح
        $user->clearOtp();

        // إنشاء الـ Token وإعادة الرد النهائي
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

    // 5. SIGN UP: التحقق من الـ OTP للتسجيل الجديد
    // ─────────────────────────────────────────────────────────────────────────
    public function verifySignupOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);

        if (!$this->otpService->verify($user, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        // تحديث حقل email_verified_at ومسح الـ OTP
        $user->update(['email_verified_at' => now()]);
        $user->clearOtp();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. Registration complete!',
            'user_id' => $user->id,
        ], 200);
    }

    // 6. RESEND OTP: إعادة إرسال الرمز (نقطة موحدة)
    // ─────────────────────────────────────────────────────────────────────────
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required_without:user_id|string',
            'user_id' => 'required_without:login|integer|exists:users,id',
            'purpose' => 'required|in:verification,reset,login_2fa',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('user_id')) {
            $user = User::find($request->user_id);
            if (!$user)
                return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
            $sent = $this->otpService->sendViaPreferredMethod($user, $request->purpose);
        } else {
            // For reset, or if we use login identifier
            $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            $user = User::where($field, $request->login)->first();

            if (!$user) {
                // If the user doesn't exist, maybe it's a pre-registration resend?
                if ($request->purpose === 'verification') {
                    // It's a pre-registration resend. We just resend using the cache service.
                    $identifier = $request->login;
                    if ($field === 'email') {
                        $sent = $this->otpService->sendRegistrationOtpViaEmail($identifier, 'User');
                    } else {
                        $sent = $this->otpService->sendRegistrationOtpViaPhone($identifier);
                    }
                    if ($sent)
                        return response()->json(['success' => true, 'message' => 'OTP resent successfully.'], 200);
                    return response()->json(['success' => false, 'message' => 'Failed to resend OTP.'], 500);
                }
                return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
            }

            if ($request->purpose === 'reset') {
                $sent = $field === 'email'
                    ? $this->otpService->sendViaEmail($user, 'reset')
                    : $this->otpService->sendViaPhone($user, 'reset');
            } else {
                $sent = $this->otpService->sendViaPreferredMethod($user, $request->purpose);
            }
        }

        if (!$sent) {
            return response()->json(['success' => false, 'message' => 'Failed to resend OTP. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP resent successfully.',
        ], 200);
    }

    // 7. 2FA: تفعيل أو تعطيل المصادقة الثنائية (محمي بـ Sanctum)
    // ─────────────────────────────────────────────────────────────────────────
    public function toggleTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'method' => 'required_if:enabled,true|in:email,phone',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        // ✅ التصحيح: استخدام $request->input('method') بدلاً من $request->method
        $user->update([
            'two_factor_enabled' => $request->enabled,
            'two_factor_method' => $request->enabled ? $request->input('method') : $user->two_factor_method,
        ]);

        // ✅ التصحيح: استخدام $request->input('method') في الرسالة أيضاً
        return response()->json([
            'success' => true,
            'message' => $request->enabled
                ? "Two-Factor Authentication enabled via {$request->input('method')}."
                : 'Two-Factor Authentication has been disabled.',
            'two_factor_enabled' => $user->two_factor_enabled,
            'two_factor_method' => $user->two_factor_method,
        ], 200);
    }

    // Helper: تقنيع البريد الإلكتروني أو رقم الهاتف
    // ─────────────────────────────────────────────────────────────────────────
    private function maskContact(string $contact, string $type): string
    {
        if ($type === 'email') {
            [$local, $domain] = explode('@', $contact);
            $masked = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 3)) . substr($local, -1);
            return $masked . '@' . $domain;
        }

        // رقم هاتف
        return substr($contact, 0, 3) . str_repeat('*', max(0, strlen($contact) - 5)) . substr($contact, -2);
    }
}
