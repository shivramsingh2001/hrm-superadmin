<?php

namespace App\Services\Broadcast;

use App\Jobs\SendBroadcastBatchJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Delivers an already-snapshotted superadmin-originated Broadcast, writing
 * DIRECTLY into the shared DB rather than bridging through hrm (3) over
 * HTTP — an already-accepted pattern in this panel (ProvisioningService
 * writes hrm (3)-owned `users` rows directly during tenant provisioning).
 *
 * For `recipient_type=tenant_user` rows: inserts a row straight into the
 * shared `notifications` table, matching the EXACT JSON shape hrm (3)'s
 * App\Notifications\BroadcastNotification::toArray() produces (same
 * `title`/`message`/`type` keys) and the exact `type` string
 * (`App\Notifications\BroadcastNotification`) hrm (3)'s own code uses — this
 * is what makes the send show up for free in the tenant's mobile API
 * (unmodified) and, since `notifiable_type` is stored as the literal string
 * `App\Models\User` (both apps happen to share that FQCN, being separate
 * Laravel installs with the default `App\` root namespace), it's
 * unambiguous in the shared table regardless of which app wrote it. No FCM
 * push from this app (no FirebaseService/credentials here) — `channels`
 * stays `['database']` for every superadmin broadcast.
 *
 * For `recipient_type=super_admin` rows: no external table write needed —
 * this panel's own notification bell reads `broadcast_recipients` directly
 * (see resources/views/layouts/app.blade.php's unread-count `@php` block
 * and NotificationController), so the recipient snapshot row alone is
 * sufficient; `delivered_at` is still set for stats-record consistency with
 * the tenant_user path.
 */
class BroadcastDeliveryService
{
    private const CHUNK_SIZE = 500;

    public function dispatchDelivery(Broadcast $broadcast): void
    {
        $chunks = BroadcastRecipient::where('broadcast_id', $broadcast->id)
            ->whereNull('delivered_at')
            ->pluck('id')
            ->chunk(self::CHUNK_SIZE);

        if ($chunks->isEmpty()) {
            $broadcast->update(['status' => 'sent', 'sent_at' => now()]);

            return;
        }

        $jobs = $chunks->map(fn ($ids) => new SendBroadcastBatchJob($broadcast->id, $ids->values()->all()))->all();

        Bus::batch($jobs)
            ->name('broadcast-' . $broadcast->id)
            ->onQueue('sa-broadcasts')
            ->then(function () use ($broadcast) {
                $broadcast->fresh()->update(['status' => 'sent', 'sent_at' => now()]);
            })
            ->catch(function (\Throwable $e) use ($broadcast) {
                Log::error('BroadcastDeliveryService: batch failed: ' . $e->getMessage(), [
                    'broadcast_id' => $broadcast->id,
                ]);
            })
            ->dispatch();
    }

    public function deliverChunk(Broadcast $broadcast, array $recipientRowIds): void
    {
        $recipients = BroadcastRecipient::whereIn('id', $recipientRowIds)->whereNull('delivered_at')->get();

        foreach ($recipients as $recipient) {
            try {
                $notificationId = null;

                if ($recipient->recipient_type === 'tenant_user' && $recipient->user_id) {
                    $notificationId = (string) Str::uuid();
                    DB::table('notifications')->insert([
                        'id' => $notificationId,
                        'type' => 'App\\Notifications\\BroadcastNotification',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $recipient->user_id,
                        'data' => json_encode([
                            'title' => $broadcast->title,
                            'message' => $broadcast->body,
                            'type' => 'broadcast',
                            'broadcast_id' => $broadcast->id,
                            'action_url' => $broadcast->action_url,
                            'action_label' => $broadcast->action_label,
                            'priority' => $broadcast->priority,
                            'created_at' => now()->toDateTimeString(),
                        ]),
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $recipient->update([
                    'notification_id' => $notificationId,
                    'delivered_at' => now(),
                    'channel_status' => ['database' => 'delivered'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('BroadcastDeliveryService: recipient delivery failed: ' . $e->getMessage(), [
                    'broadcast_id' => $broadcast->id,
                    'recipient_id' => $recipient->id,
                ]);
            }
        }
    }
}
