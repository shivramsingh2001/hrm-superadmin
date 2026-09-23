<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringMail;
use App\Mail\TrialExpiringMail;
use App\Models\SuperAdminNotification;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the tenant admin + raises a super-admin alert 7 / 3 / 1 days before a
 * trial ends, or before a paid subscription's tenant_subscriptions.end_date is
 * reached. Idempotent per (tenant, day-count) per calendar day, per type.
 */
class TrialExpiryNotify extends Command
{
    protected $signature = 'tenants:trial-expiry';

    protected $description = 'Notify tenants and staff of trials or paid subscriptions ending in 7 / 3 / 1 days.';

    public function handle(): int
    {
        $sent = $this->notifyTrials() + $this->notifyPaidSubscriptions();

        $this->info("Expiry reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function notifyTrials(): int
    {
        $days = config('lifecycle.trial_reminder_days', [7, 3, 1]);
        $sent = 0;

        $tenants = Tenant::whereNotNull('trial_ends_at')
            ->where('status', '!=', 'suspended')
            ->whereBetween('trial_ends_at', [now()->startOfDay(), now()->addDays(max($days))->endOfDay()])
            ->get();

        foreach ($tenants as $t) {
            // Whole calendar days from today to the trial end date.
            $left = (int) round(now()->startOfDay()->diffInDays($t->trial_ends_at->copy()->startOfDay(), false));
            if (! in_array($left, $days, true)) {
                continue;
            }

            $already = SuperAdminNotification::where('type', 'trial_expiring')
                ->whereDate('created_at', today())
                ->where('data->tenant_id', $t->id)
                ->where('data->days', $left)
                ->exists();
            if ($already) {
                continue;
            }

            try {
                Mail::to($t->email)->send(new TrialExpiringMail($t->company_name, $t->subdomain, $left, $t->trial_ends_at));
            } catch (\Throwable $e) {
                Log::warning("trial-expiry mail failed for tenant {$t->id}: " . $e->getMessage());
            }

            NotificationService::broadcast('trial_expiring',
                "Trial ending in {$left}d: {$t->company_name}",
                "Trial ends {$t->trial_ends_at->format('d M Y')}.",
                ['tenant_id' => $t->id, 'days' => $left]);
            $sent++;
        }

        return $sent;
    }

    private function notifyPaidSubscriptions(): int
    {
        $days = config('lifecycle.trial_reminder_days', [7, 3, 1]);
        $sent = 0;

        $subs = TenantSubscription::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->startOfDay()->toDateString(), now()->addDays(max($days))->toDateString()])
            ->get();

        foreach ($subs as $sub) {
            $t = Tenant::find($sub->tenant_id);
            if (! $t || $t->status === 'suspended') {
                continue;
            }

            $left = (int) round(now()->startOfDay()->diffInDays($sub->end_date->copy()->startOfDay(), false));
            if (! in_array($left, $days, true)) {
                continue;
            }

            $already = SuperAdminNotification::where('type', 'subscription_expiring')
                ->whereDate('created_at', today())
                ->where('data->tenant_id', $t->id)
                ->where('data->days', $left)
                ->exists();
            if ($already) {
                continue;
            }

            try {
                Mail::to($t->email)->send(new SubscriptionExpiringMail($t->company_name, $t->subdomain, $left, $sub->end_date));
            } catch (\Throwable $e) {
                Log::warning("subscription-expiry mail failed for tenant {$t->id}: " . $e->getMessage());
            }

            NotificationService::broadcast('subscription_expiring',
                "Subscription ending in {$left}d: {$t->company_name}",
                "Subscription ends {$sub->end_date->format('d M Y')}.",
                ['tenant_id' => $t->id, 'days' => $left]);
            $sent++;
        }

        return $sent;
    }
}
