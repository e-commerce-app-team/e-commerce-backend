<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رمز التحقق</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            padding: 40px 20px;
            direction: rtl;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #6C63FF 0%, #4CAF50 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .header p {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
        }
        .body {
            padding: 40px 35px;
        }
        .greeting {
            font-size: 16px;
            color: #2d3436;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .message {
            font-size: 14px;
            color: #636e72;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .otp-box {
            background: #f8f9ff;
            border: 2px dashed #6C63FF;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin: 20px 0;
        }
        .otp-label {
            font-size: 13px;
            color: #636e72;
            margin-bottom: 12px;
        }
        .otp-code {
            font-size: 42px;
            font-weight: 800;
            color: #6C63FF;
            letter-spacing: 10px;
            font-family: 'Courier New', monospace;
        }
        .otp-expires {
            font-size: 12px;
            color: #b2bec3;
            margin-top: 12px;
        }
        .warning {
            background: #fff3cd;
            border-right: 4px solid #ffc107;
            padding: 14px 18px;
            border-radius: 8px;
            margin: 24px 0;
            font-size: 13px;
            color: #856404;
        }
        .footer {
            background: #f8f9ff;
            padding: 24px 35px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            font-size: 12px;
            color: #adb5bd;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($purpose === 'verification')
                <h1>✉️ تحقق من بريدك الإلكتروني</h1>
                <p>شكراً لانضمامك إلينا! أكّد بريدك لإتمام التسجيل</p>
            @elseif($purpose === 'reset')
                <h1>🔐 إعادة تعيين كلمة المرور</h1>
                <p>استخدم الرمز أدناه لإعادة تعيين كلمة مرورك</p>
            @else
                <h1>🛡️ رمز المصادقة الثنائية</h1>
                <p>رمز دخول لمرة واحدة للتحقق من هويتك</p>
            @endif
        </div>

        <div class="body">
            <p class="greeting">مرحباً {{ $userName }}،</p>

            <p class="message">
                @if($purpose === 'verification')
                    شكراً لإنشاء حسابك معنا. لإتمام عملية التسجيل والتحقق من بريدك الإلكتروني، استخدم رمز التحقق أدناه:
                @elseif($purpose === 'reset')
                    تلقّينا طلباً لإعادة تعيين كلمة المرور لحسابك. إذا لم تكن أنت من طلب ذلك، يمكنك تجاهل هذا البريد.
                @else
                    تمّت محاولة تسجيل دخول لحسابك. لإتمام تسجيل الدخول، أدخل رمز التحقق أدناه:
                @endif
            </p>

            <div class="otp-box">
                <p class="otp-label">رمز التحقق الخاص بك</p>
                <p class="otp-code">{{ $otpCode }}</p>
                <p class="otp-expires">⏱ صالح لمدة دقيقة واحدة فقط</p>
            </div>

            <div class="warning">
                <strong>⚠️ تنبيه أمني:</strong> لا تشارك هذا الرمز مع أي شخص آخر. فريق الدعم لدينا لن يطلب منك هذا الرمز أبداً.
            </div>

            <p class="message" style="font-size: 13px;">
                إذا واجهت أي مشكلة، تواصل معنا عبر الدعم الفني.
            </p>
        </div>

        <div class="footer">
            <p>
                تم إرسال هذا البريد تلقائياً · لا تردّ عليه<br>
                © {{ date('Y') }} منصة التجارة الإلكترونية · جميع الحقوق محفوظة
            </p>
        </div>
    </div>
</body>
</html>
