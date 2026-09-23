<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up 2FA · HRM Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>body{background:#1f2937}.box{max-width:420px;margin:7vh auto}</style>
</head>
<body>
<div class="box">
    <div class="text-center text-white mb-3"><h4>Set up two-factor authentication</h4>
        <div class="small text-secondary">Required for all super-admin accounts</div></div>
    <div class="card shadow"><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
        <p class="small">Scan this with Google Authenticator / Authy / 1Password, then enter the 6-digit code to confirm.</p>
        <div id="qr" class="d-flex justify-content-center my-3"></div>
        <p class="small text-muted">Can't scan? Enter this key manually:<br><code>{{ $secret }}</code></p>
        <form method="POST" action="{{ route('totp.enable') }}">
            @csrf
            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                   class="form-control text-center mb-3" placeholder="123456" required autofocus style="letter-spacing:.3em;font-size:1.3rem">
            <button class="btn btn-primary w-100">Confirm &amp; continue</button>
        </form>
    </div></div>
</div>
<script>new QRCode(document.getElementById('qr'), { text: @json($uri), width: 180, height: 180 });</script>
</body>
</html>
