<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Staff Invitation</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { background-color: #ffffff; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { color: #333333; }
        p { color: #555555; line-height: 1.5; }
        .btn { display: inline-block; padding: 12px 24px; margin-top: 20px; background-color: #0056b3; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 12px; color: #999999; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>You're Invited!</h2>
        <p>Hello,</p>
        <p>You have been invited to join <strong>{{ $storeName }}</strong> as a staff member.</p>
        <p>By accepting this invitation, you will be able to manage specific parts of the store based on the permissions granted to you.</p>
        
        <p style="text-align: center;">
            <a href="{{ $deepLink }}" class="btn">Accept Invitation</a>
        </p>
        
        <p>If you cannot click the button, you can also copy and paste the following link into your browser or device:</p>
        <p style="word-break: break-all; color: #0056b3;">{{ $deepLink }}</p>

        <p>This invitation will expire at {{ $invitation->expires_at->format('Y-m-d H:i') }}.</p>
        
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
