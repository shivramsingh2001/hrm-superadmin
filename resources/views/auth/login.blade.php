<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · HRM Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-1: #1e3a8a;
            --brand-2: #2563eb;
        }
        html, body { height:100%; }
        body {
            margin:0;
            font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background:#0f172a;
            background-image:
                radial-gradient(circle at 15% 20%, rgba(37,99,235,.35), transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(30,58,138,.45), transparent 50%),
                linear-gradient(135deg, var(--brand-1), var(--brand-2));
            display:flex;
            align-items:center;
            justify-content:center;
            padding:1.5rem;
        }

        .auth-wrap { width:100%; max-width:880px; }

        .auth-card {
            display:flex;
            border-radius:1.1rem;
            overflow:hidden;
            box-shadow:0 25px 60px -15px rgba(2, 6, 23, .55);
            background:#fff;
        }

        /* ---- Left brand panel ---- */
        .auth-brand {
            flex:0 0 42%;
            background:linear-gradient(150deg, var(--brand-1), var(--brand-2));
            color:#fff;
            padding:2.6rem 2.2rem;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            position:relative;
            overflow:hidden;
        }
        .auth-brand::before {
            content:"";
            position:absolute;
            width:280px; height:280px;
            border-radius:50%;
            background:rgba(255,255,255,.08);
            top:-90px; right:-90px;
        }
        .auth-brand::after {
            content:"";
            position:absolute;
            width:200px; height:200px;
            border-radius:50%;
            background:rgba(255,255,255,.06);
            bottom:-60px; left:-60px;
        }
        .auth-brand .mark {
            width:2.75rem; height:2.75rem;
            border-radius:.75rem;
            background:rgba(255,255,255,.15);
            display:inline-flex; align-items:center; justify-content:center;
            font-size:1.3rem;
            backdrop-filter:blur(4px);
            position:relative; z-index:1;
        }
        .auth-brand h1 {
            font-size:1.5rem;
            font-weight:800;
            margin:1.1rem 0 .4rem;
            position:relative; z-index:1;
        }
        .auth-brand p {
            font-size:.86rem;
            color:rgba(255,255,255,.78);
            line-height:1.55;
            margin:0;
            position:relative; z-index:1;
        }
        .auth-brand .features {
            list-style:none;
            padding:0; margin:1.6rem 0 0;
            position:relative; z-index:1;
        }
        .auth-brand .features li {
            display:flex; align-items:center; gap:.6rem;
            font-size:.8rem;
            color:rgba(255,255,255,.88);
            padding:.4rem 0;
        }
        .auth-brand .features li i {
            width:1.6rem; height:1.6rem;
            border-radius:.4rem;
            background:rgba(255,255,255,.14);
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.78rem; flex:none;
        }
        .auth-brand .foot {
            font-size:.7rem;
            color:rgba(255,255,255,.55);
            position:relative; z-index:1;
        }

        /* ---- Right form panel ---- */
        .auth-form {
            flex:1;
            padding:2.8rem 2.6rem;
            display:flex;
            flex-direction:column;
            justify-content:center;
        }
        .auth-form h2 { font-size:1.35rem; font-weight:700; color:#111827; margin-bottom:.25rem; }
        .auth-form .sub { font-size:.85rem; color:#9ca3af; margin-bottom:1.75rem; }

        .form-label { font-size:.78rem; font-weight:600; color:#374151; margin-bottom:.35rem; }

        .input-icon { position:relative; }
        .input-icon > i {
            position:absolute; left:.85rem; top:50%; transform:translateY(-50%);
            color:#9ca3af; font-size:.92rem; pointer-events:none;
        }
        .input-icon .form-control {
            padding-left:2.4rem;
            height:2.85rem;
            font-size:.9rem;
            border-radius:.6rem;
            border:1px solid #e5e7eb;
            background:#f9fafb;
        }
        .input-icon .form-control:focus {
            background:#fff;
            border-color:var(--brand-2);
            box-shadow:0 0 0 .2rem rgba(37,99,235,.12);
        }
        .toggle-pw {
            position:absolute; right:.7rem; top:50%; transform:translateY(-50%);
            border:none; background:none; color:#9ca3af; font-size:.92rem; padding:.2rem .3rem; cursor:pointer;
        }
        .toggle-pw:hover { color:#4b5563; }

        .btn-signin {
            height:2.85rem;
            border-radius:.6rem;
            font-weight:600;
            font-size:.92rem;
            background:linear-gradient(135deg, var(--brand-1), var(--brand-2));
            border:none;
            transition:filter .15s ease, transform .05s ease;
        }
        .btn-signin:hover { filter:brightness(1.08); }
        .btn-signin:active { transform:translateY(1px); }

        .alert-danger {
            border:none; background:#fef2f2; color:#b91c1c;
            border-radius:.6rem; font-size:.83rem;
            display:flex; align-items:center; gap:.5rem;
        }

        .auth-footnote {
            text-align:center; font-size:.72rem; color:#9ca3af; margin-top:1.5rem;
            display:flex; align-items:center; justify-content:center; gap:.35rem;
        }

        @media (max-width: 767px) {
            .auth-brand { display:none; }
            .auth-form { padding:2.2rem 1.6rem; }
        }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div>
                <span class="mark"><i class="bi bi-shield-lock-fill"></i></span>
                <h1>HRM Super Admin</h1>
                <p>Platform console for managing tenants, plans, and cross-tenant operations.</p>
                <ul class="features">
                    <li><i class="bi bi-building"></i> Tenant lifecycle &amp; provisioning</li>
                    <li><i class="bi bi-toggles"></i> Feature &amp; plan governance</li>
                    <li><i class="bi bi-journal-text"></i> Full audit trail</li>
                    <li><i class="bi bi-shield-check"></i> Two-factor secured access</li>
                </ul>
            </div>
            <div class="foot">Restricted to authorized platform staff</div>
        </div>

        <div class="auth-form">
            <h2>Welcome back</h2>
            <div class="sub">Sign in to continue to the super admin console</div>

            @if($errors->any())
                <div class="alert alert-danger py-2 mb-3">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-icon">
                        <i class="bi bi-envelope"></i>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control"
                               placeholder="you@company.com" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="••••••••" required>
                        <button type="button" class="toggle-pw" id="togglePw" tabindex="-1" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label small text-secondary" for="remember">Remember me</label>
                </div>
                <button class="btn btn-primary btn-signin w-100 text-white">Sign in</button>
            </form>

            <div class="auth-footnote">
                <i class="bi bi-shield-lock"></i>
                <span>Protected by two-factor authentication</span>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('togglePw').addEventListener('click', function () {
        var pw = document.getElementById('password');
        var icon = this.querySelector('i');
        var isHidden = pw.type === 'password';
        pw.type = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
</body>
</html>
