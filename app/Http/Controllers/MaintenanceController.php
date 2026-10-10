<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceMode;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PlatformClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Platform maintenance mode — puts the whole HRM (web + mobile API) behind a
 * maintenance screen, optionally for a time window, with an allow-list of
 * IPs and users who can still get in. Editing is superadmin-only.
 */
class MaintenanceController extends Controller
{
    private const FIELDS = ['is_enabled', 'title', 'message', 'start_time', 'end_time', 'allowed_ips', 'allowed_users', 'enabled_by'];

    public function index(Request $request)
    {
        $mode = MaintenanceMode::row();
        $users = User::query()->whereIn('id', $mode->allowed_users ?? [])
            ->get(['id', 'name', 'email', 'tenant_id'])->keyBy('id');

        return view('maintenance.index', [
            'mode' => $mode,
            'users' => $users,
            'tz' => MaintenanceMode::tz(),
            'myIp' => $request->ip(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
            'allowed_ips' => ['nullable', 'string', 'max:5000'],
            'allowed_users' => ['nullable', 'string', 'max:5000'],
        ]);

        $mode = MaintenanceMode::row();
        $old = $mode->only(self::FIELDS);
        $tz = MaintenanceMode::tz();

        $mode->fill([
            'is_enabled' => $request->boolean('is_enabled'),
            'title' => $data['title'],
            'message' => $data['message'],
            // datetime-local input is HRM wall-clock time; store it as-is.
            'start_time' => $this->wallClock($data['start_time'] ?? null, $tz),
            'end_time' => $this->wallClock($data['end_time'] ?? null, $tz),
            'allowed_ips' => $this->parseIps($data['allowed_ips'] ?? ''),
            'allowed_users' => $this->parseUsers($data['allowed_users'] ?? ''),
        ]);
        if ($mode->isDirty('is_enabled') || $mode->is_enabled) {
            $mode->enabled_by = $request->user()->getKey();
        }
        $mode->save();

        $this->after($mode, $old);

        return redirect()->route('maintenance.index')->with('success', match ($mode->state()) {
            'live' => 'Maintenance mode is ON — the HRM is now showing the maintenance screen.',
            'scheduled' => 'Maintenance scheduled from ' . $mode->startAt()->format('d M Y, h:i A') . '.',
            'ended' => 'Saved. The end time has already passed, so maintenance is not active.',
            default => 'Maintenance mode is OFF.',
        });
    }

    /** One-click switch off from the banner. */
    public function disable(Request $request)
    {
        $mode = MaintenanceMode::row();
        $old = $mode->only(self::FIELDS);
        $mode->update(['is_enabled' => false, 'enabled_by' => $request->user()->getKey()]);
        $this->after($mode, $old);

        return redirect()->route('maintenance.index')->with('success', 'Maintenance mode is OFF.');
    }

    private function after(MaintenanceMode $mode, array $old): void
    {
        AuditLogger::record('maintenance.updated', 'maintenance_mode', $mode->id, $old, $mode->fresh()->only(self::FIELDS));
        // Tell the HRM to drop its cached copy (otherwise it refreshes within 30s).
        PlatformClient::bust(['maintenance' => true]);
    }

    private function wallClock(?string $value, string $tz): ?string
    {
        return $value ? Carbon::parse($value, $tz)->format('Y-m-d H:i:s') : null;
    }

    /** @return list<string> one IP or CIDR range per line / comma */
    private function parseIps(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $ip) {
            [$addr, $bits] = array_pad(explode('/', $ip, 2), 2, null);
            $valid = filter_var($addr, FILTER_VALIDATE_IP) !== false;
            if ($valid && $bits !== null) {
                $max = str_contains($addr, ':') ? 128 : 32;
                $valid = ctype_digit($bits) && (int) $bits <= $max;
            }
            if (! $valid) {
                throw ValidationException::withMessages(['allowed_ips' => "\"{$ip}\" is not a valid IP address or CIDR range."]);
            }
            $out[] = $ip;
        }

        return array_values(array_unique($out));
    }

    /** @return list<int> HRM user ids, from emails or ids one per line / comma */
    private function parseUsers(string $raw): array
    {
        $ids = [];
        foreach (preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            // An email can exist in several companies — allow each of them.
            $found = ctype_digit($entry)
                ? User::query()->whereKey((int) $entry)->pluck('id')
                : User::query()->where('email', $entry)->pluck('id');
            if ($found->isEmpty()) {
                throw ValidationException::withMessages(['allowed_users' => "No HRM user found for \"{$entry}\"."]);
            }
            array_push($ids, ...$found->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($ids));
    }
}
