<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>2FA · HRM Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#1f2937}.box{max-width:360px;margin:12vh auto}</style>
</head>
<body>
<div class="box">
    <div class="text-center text-white mb-3"><h4>Two-factor code</h4>
        <div class="small text-secondary">Enter the 6-digit code from your authenticator</div></div>
    <div class="card shadow"><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('totp.check') }}">
            @csrf
            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                   class="form-control text-center mb-3" placeholder="123456" required autofocus style="letter-spacing:.3em;font-size:1.4rem">
            <button class="btn btn-primary w-100">Verify</button>
        </form>
        <a href="{{ route('login') }}" class="btn btn-link btn-sm w-100 mt-2">Start over</a>
    </div></div>
</div>
</body>
</html>
