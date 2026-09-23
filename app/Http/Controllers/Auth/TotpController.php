<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SecurityAlertMail;
use App\Models\SuperAdmin;
use App\Services\NotificationService;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TotpController extends Controller
{
    /** First-login enrolment. */
    public function setup(Request $request)
    {
        $user = $this->pending($request);
        if ($user->hasTotp()) {
            return redirect()->route('totp.verify');
        }

        $secret = $request->session()->get('totp.new_secret');
        if (! $secret) {
            $secret = Totp::generateSecret();
            $request->session()->put('totp.new_secret', $secret);
        }

        return view('auth.totp-setup', [
            'secret' => $secret,
            'uri' => Totp::uri($secret, $user->email),
        ]);
    }

    public function enable(Request $request)
    {
        $user = $this->pending($request);
        $request->validate(['code' => ['required', 'string']]);

        $secret = $request->session()->get('totp.new_secret');
        if (! $secret || ! Totp::verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect. Check your authenticator app.']);
        }

        $user->forceFill([
            'totp_secret' => $secret,           // encrypted by the model cast
            'totp_enabled_at' => now(),
        ])->save();
        $request->session()->forget('totp.new_secret');

        return $this->complete($request, $user);
    }

    public function verify(Request $request)
    {
        $this->pending($request);

        return view('auth.totp-verify');
    }

    public function check(Request $request)
    {
        $user = $this->pending($request);
        $request->validate(['code' => ['required', 'string']]);

        $key = 'sa-totp:' . $user->id . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Wait a minute and try again.']);
        }

        if (! Totp::verify((string) $user->totp_secret, $request->input('code'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'Incorrect code.']);
        }
        RateLimiter::clear($key);

        return $this->complete($request, $user);
    }

    // ------------------------------------------------------------------

    private function pending(Request $request): SuperAdmin
    {
        $id = $request->session()->get('totp.pending_id');
        $user = $id ? SuperAdmin::find($id) : null;
        if (! $user) {
            abort(redirect()->route('login'));
        }

        return $user;
    }

    private function complete(Request $request, SuperAdmin $user)
    {
        $remember = (bool) $request->session()->pull('totp.remember', false);
        $request->session()->forget('totp.pending_id');

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
                    "Your Super Admin account was signed into from a new IP address ({$request->ip()}). If this wasn't you, rotate your password and TOTP.",
                ));
            } catch (\Throwable $e) {
            }
        }

        return redirect()->intended(route('dashboard'));
    }
}
