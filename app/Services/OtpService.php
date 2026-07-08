<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * توليد رمز OTP عشوائي من 6 أرقام وحفظه للمستخدم مع وقت انتهاء الصلاحية.
     */
    public function generateAndSave(User $user): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'otp_code'        => $otp,
            'otp_expires_at'  => now()->addMinute(), // صالح لمدة دقيقة واحدة فقط
        ]);

        return $otp;
    }

    /**
     * إرسال الـ OTP عبر البريد الإلكتروني.
     */
    public function sendViaEmail(User $user, string $purpose = 'verification'): bool
    {
        $otp = $this->generateAndSave($user);

        try {
            Mail::to($user->email)->send(new OtpMail($otp, $user->first_name, $purpose));
            Log::info("OTP Email sent to {$user->email} for purpose: {$purpose}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email to {$user->email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إرسال الـ OTP عبر WhatsApp باستخدام خدمة UltraMsg.
     */
    public function sendViaPhone(User $user, string $purpose = 'verification'): bool
    {
        $otp = $this->generateAndSave($user);

        $purposeMessages = [
            'verification' => 'رمز التحقق لتفعيل حسابك',
            'reset'        => 'رمز إعادة تعيين كلمة المرور',
            'login_2fa'    => 'رمز تسجيل الدخول',
        ];

        $purposeText = $purposeMessages[$purpose] ?? 'رمز التحقق';
        $message     = "🛡 منصة التجارة الإلكترونية\n\n{$purposeText} هو:\n\n*{$otp}*\n\n⏱ صالح لمدة دقيقة واحدة فقط.\n\n⚠️ لا تشارك هذا الرمز مع أحد.";

        $params = [
            'token' => env('ULTRAMSG_TOKEN'),
            'to'    => $user->phone,
            'body'  => $message,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => env('ULTRAMSG_API_URL') . '/messages/chat',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['content-type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error("UltraMsg OTP Error for {$user->phone}: $err");
            return false;
        }

        Log::info("UltraMsg OTP sent to {$user->phone}: $response");
        return true;
    }

    /**
     * إرسال الـ OTP عبر القناة المفضلة للمستخدم (للـ 2FA).
     */
    public function sendViaPreferredMethod(User $user, string $purpose = 'login_2fa'): bool
    {
        return $user->two_factor_method === 'phone'
            ? $this->sendViaPhone($user, $purpose)
            : $this->sendViaEmail($user, $purpose);
    }

    /**
     * التحقق من صحة الـ OTP الخاص بالمستخدم المسجل.
     */
    public function verify(User $user, string $code): bool
    {
        return $user->isOtpValid($code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CACHE-BASED OTP FOR REGISTRATION (لم يتم إنشاء المستخدم بعد)
    // ─────────────────────────────────────────────────────────────────────────

    public function generateAndCacheOtp(string $identifier): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // حفظ في الكاش لمدة 5 دقائق
        \Illuminate\Support\Facades\Cache::put('registration_otp_' . $identifier, $otp, now()->addMinutes(5));

        return $otp;
    }

    public function sendRegistrationOtpViaEmail(string $email, string $firstName): bool
    {
        $otp = $this->generateAndCacheOtp($email);

        try {
            Mail::to($email)->send(new OtpMail($otp, $firstName, 'verification'));
            Log::info("Registration OTP Email sent to {$email}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send Registration OTP email to {$email}: " . $e->getMessage());
            return false;
        }
    }

    public function sendRegistrationOtpViaPhone(string $phone): bool
    {
        $otp = $this->generateAndCacheOtp($phone);

        $message = "🛡 منصة التجارة الإلكترونية\n\nرمز التحقق لتفعيل حسابك هو:\n\n*{$otp}*\n\n⏱ صالح لمدة 5 دقائق.";

        $params = [
            'token' => env('ULTRAMSG_TOKEN'),
            'to'    => $phone,
            'body'  => $message,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => env('ULTRAMSG_API_URL') . '/messages/chat',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['content-type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error("Registration UltraMsg OTP Error for {$phone}: $err");
            return false;
        }

        Log::info("Registration UltraMsg OTP sent to {$phone}: $response");
        return true;
    }

    public function verifyRegistrationOtp(string $identifier, string $code): bool
    {
        $cachedOtp = \Illuminate\Support\Facades\Cache::get('registration_otp_' . $identifier);

        if ($cachedOtp && $cachedOtp === $code) {
            // مسح الـ OTP بعد الاستخدام
            \Illuminate\Support\Facades\Cache::forget('registration_otp_' . $identifier);
            // حفظ علامة تفيد بأن هذا المعرف تم التحقق منه (صالحة لمدة 30 دقيقة)
            \Illuminate\Support\Facades\Cache::put('verified_registration_' . $identifier, true, now()->addMinutes(30));
            return true;
        }

        return false;
    }
}
