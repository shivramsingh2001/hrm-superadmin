<?php

namespace App\Console\Commands;

use App\Mail\MetricsDigestMail;
use App\Models\PlatformMetric;
use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the latest platform metrics to every active super admin. Schedule
 * weekly (after metrics:snapshot has run).
 */
class MetricsDigest extends Command
{
    protected $signature = 'metrics:digest';

    protected $description = 'Email the weekly platform digest to super admins.';

    public function handle(): int
    {
        $metric = PlatformMetric::orderByDesc('metric_date')->first();
        if (! $metric) {
            $this->warn('No metrics snapshot yet — run metrics:snapshot first.');

            return self::SUCCESS;
        }

        $to = SuperAdmin::where('is_active', true)->pluck('email');
        foreach ($to as $email) {
            try {
                Mail::to($email)->send(new MetricsDigestMail($metric));
            } catch (\Throwable $e) {
                $this->warn("digest to {$email} failed: " . $e->getMessage());
            }
        }
        $this->info("Digest sent to {$to->count()} super admin(s).");

        return self::SUCCESS;
    }
}
