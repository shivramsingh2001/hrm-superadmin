<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SecurityAlertMail;
use App\Models\SuperAdmin;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'sa-login:' . mb_strtolower($data['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->alertLockout($data['email'], $request->ip());
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in ' . ceil($seconds / 60) . ' minute(s).',
            ]);
        }

        $user = SuperAdmin::where('email', $data['email'])->first();
        if (! $user || ! Auth::getProvider()->validateCredentials($user, $data)) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['email' => 'Those credentials do not match our records.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'This account is deactivated.']);
        }
        if (! $user->ipAllowed($request->ip())) {
            NotificationService::broadcast('security_alert', 'Blocked login (IP not allowed): ' . $user->email,
                'From ' . $request->ip(), ['super_admin_id' => $user->id]);
            throw ValidationException::withMessages(['email' => 'Login from this IP address is not permitted for this account.']);
        }

        RateLimiter::clear($key);

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    private function completeLogin(Request $request, SuperAdmin $user, bool $remember)
    {
        $newIp = $user->last_login_ip && $user->last_login_ip !== $request->ip();

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('sa_login_at', now()->toIso8601String());

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if ($newIp) {
            NotificationService::broadcast('security_alert', 'Sign-in from a new IP: ' . $user->email,
                'IP ' . $request->ip() . ' (previous: ' . $user->getOriginal('last_login_ip') . ')',
                ['super_admin_id' => $user->id]);
            try {
                Mail::to($user->email)->send(new SecurityAlertMail(
                    'New sign-in location',
                    "Your Super Admin account was signed into from a new IP address ({$request->ip()}). If this wasn't you, rotate your password.",
                ));
            } catch (\Throwable $e) {
            }
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function alertLockout(string $email, string $ip): void
    {
        $admin = SuperAdmin::where('email', $email)->first();
        if (! $admin) {
            return;
        }
        // Only fire once per lock window.
        $flag = 'sa-lockout-alert:' . $admin->id;
        if (RateLimiter::tooManyAttempts($flag, 1)) {
            return;
        }
        RateLimiter::hit($flag, 900);

        try {
            Mail::to($admin->email)->send(new SecurityAlertMail(
                'Repeated failed sign-in attempts',
                "5+ failed sign-ins for your Super Admin account from {$ip}. The account is locked for 15 minutes.",
            ));
        } catch (\Throwable $e) {
            // never block login on mail
        }
        NotificationService::broadcast('security_alert', 'Account locked: ' . $admin->email,
            "5+ failed sign-ins from {$ip}", ['super_admin_id' => $admin->id]);
    }
}
