<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#F8FAFC; color:#0F172A; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
        .card { background:#fff; border-radius:16px; padding:40px 48px; box-shadow:0 10px 30px rgba(0,0,0,0.06); text-align:center; max-width:480px; }
        h1 { font-size:22px; margin-bottom:8px; }
        p { color:#64748B; font-size:14px; line-height:1.6; }
        code { background:#F1F5F9; padding:2px 6px; border-radius:6px; font-size:13px; }
        .badge { display:inline-block; background:#DCFCE7; color:#166534; font-size:12px; font-weight:700; padding:4px 10px; border-radius:20px; margin-bottom:16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">API RUNNING</div>
        <h1>{{ config('app.name') }}</h1>
        <p>
            This is the backend API server. Point your mobile app or API
            client at <code>{{ url('/api/v1') }}</code>.
        </p>
        <p>See <code>/mnt/user-data/outputs/docs</code> for the OpenAPI spec and Postman collection.</p>
    </div>
</body>
</html>
