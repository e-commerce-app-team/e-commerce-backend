<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to App...</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #f26522; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <h2>Opening App...</h2>
    <p>If the app does not open automatically, click the button below:</p>
    
    <a href="ecomapp://staff/accept-invite?token={{ $token }}" class="btn">Open App</a>

    <script>
        // Attempt to redirect automatically
        window.location.href = "ecomapp://staff/accept-invite?token={{ $token }}";
    </script>
</body>
</html>
