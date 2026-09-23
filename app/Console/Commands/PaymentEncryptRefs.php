<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill: encrypt any plaintext payment_logs.reference_number.
 * Idempotent — rows that already decrypt cleanly are skipped.
 */
class PaymentEncryptRefs extends Command
{
    protected $signature = 'payment:encrypt-refs {--dry-run}';

    protected $description = 'Encrypt legacy plaintext payment reference numbers.';

    public function handle(): int
    {
        $done = $skipped = 0;

        DB::table('payment_logs')->whereNotNull('reference_number')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$done, &$skipped) {
                foreach ($rows as $r) {
                    try {
                        Crypt::decryptString($r->reference_number);
                        $skipped++;

                        continue; // already encrypted
                    } catch (\Throwable $e) {
                        // plaintext — encrypt it
                    }
                    if (! $this->option('dry-run')) {
                        DB::table('payment_logs')->where('id', $r->id)
                            ->update(['reference_number' => Crypt::encryptString($r->reference_number)]);
                    }
                    $done++;
                }
            });

        $this->info(($this->option('dry-run') ? 'Would encrypt ' : 'Encrypted ') . "{$done} row(s); {$skipped} already encrypted.");

        return self::SUCCESS;
    }
}
