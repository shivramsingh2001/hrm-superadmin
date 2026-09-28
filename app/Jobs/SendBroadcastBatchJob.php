<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\Broadcast\BroadcastDeliveryService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one chunk (<= 500) of a superadmin Broadcast's
 * `broadcast_recipients` rows — dispatched in a Bus::batch() by
 * App\Services\Broadcast\BroadcastDeliveryService::dispatchDelivery().
 * Same bounded explicit-retry shape as its hrm (3) counterpart (separate
 * class, same app-local `App\Jobs\SendBroadcastBatchJob` name, since job
 * classes are resolved by FQCN within their own app's queue worker — these
 * two apps cannot share a Job class even though they share the `jobs`
 * table).
 *
 * Requires `queue:work --queue=sa-broadcasts` running under THIS app
 * (hrm-superadmin), separate from hrm (3)'s worker. **Deliberately a
 * different queue name than hrm (3)'s identically-named
 * `App\Jobs\SendBroadcastBatchJob`** even though both physically share one
 * `jobs` table (same DB): a worker is selected by `--queue=`, not by app, so
 * if both apps' jobs used the same queue name, either app's worker could
 * dequeue and execute the OTHER app's job — and since both classes share
 * the same name/constructor shape, PHP would silently run it using the
 * wrong app's App\Services\Broadcast\BroadcastDeliveryService (this app's
 * raw-notifications-insert vs. hrm (3)'s Notification::send()+FCM), with no
 * error. Distinct queue names make that structurally impossible.
 */
class SendBroadcastBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Minutes to wait before retry attempt N (1-indexed). */
    private const SCHEDULE = [1, 5, 15];

    public int $tries = 1; // retry/scheduling handled explicitly below

    public function __construct(public int $broadcastId, public array $recipientRowIds, public int $attempt = 1)
    {
        $this->onQueue('sa-broadcasts');
    }

    public function handle(BroadcastDeliveryService $delivery): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $broadcast = Broadcast::find($this->broadcastId);
        if (! $broadcast) {
            return;
        }

        try {
            $delivery->deliverChunk($broadcast, $this->recipientRowIds);
        } catch (\Throwable $e) {
            Log::warning('SendBroadcastBatchJob: chunk failed: ' . $e->getMessage(), [
                'broadcast_id' => $this->broadcastId,
                'attempt' => $this->attempt,
            ]);

            if ($this->attempt < count(self::SCHEDULE)) {
                self::dispatch($this->broadcastId, $this->recipientRowIds, $this->attempt + 1)
                    ->delay(now()->addMinutes(self::SCHEDULE[$this->attempt]))
                    ->onQueue('sa-broadcasts');

                return;
            }

            Log::error('SendBroadcastBatchJob: chunk permanently failed after ' . count(self::SCHEDULE) . ' attempts', [
                'broadcast_id' => $this->broadcastId,
                'recipient_row_ids' => $this->recipientRowIds,
            ]);
        }
    }
}
