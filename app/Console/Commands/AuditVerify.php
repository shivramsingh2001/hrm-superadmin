<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recompute the HMAC of each audit_logs row and compare to sa_audit_signatures.
 * Reports TAMPERED (hmac mismatch) and UNSIGNED (no signature row) counts.
 */
class AuditVerify extends Command
{
    protected $signature = 'audit:verify {--since= : only rows on/after this date} {--sign-missing : HMAC-sign rows that have none}';

    protected $description = 'Verify the integrity of audit_logs against sa_audit_signatures.';

    public function handle(): int
    {
        if (! config('platform.audit_hmac_key')) {
            $this->error('AUDIT_LOG_HMAC_KEY is not set — nothing to verify.');

            return self::FAILURE;
        }

        $sigs = DB::table('sa_audit_signatures')->pluck('hmac', 'audit_log_id');
        $q = DB::table('audit_logs')->orderBy('id')
            ->when($this->option('since'), fn ($x, $v) => $x->whereDate('created_at', '>=', $v));

        $ok = $tampered = $unsigned = 0;
        $tamperedIds = [];

        $q->chunk(500, function ($rows) use (&$ok, &$tampered, &$unsigned, &$tamperedIds, $sigs) {
            foreach ($rows as $r) {
                $expected = AuditLogger::hmac($r);
                if (! isset($sigs[$r->id])) {
                    $unsigned++;
                    if ($this->option('sign-missing')) {
                        DB::table('sa_audit_signatures')->insert([
                            'audit_log_id' => $r->id, 'hmac' => $expected, 'created_at' => now(),
                        ]);
                    }
                } elseif (hash_equals($sigs[$r->id], $expected)) {
                    $ok++;
                } else {
                    $tampered++;
                    $tamperedIds[] = $r->id;
                }
            }
        });

        $this->line("Verified OK : {$ok}");
        $this->line("Unsigned    : {$unsigned}" . ($this->option('sign-missing') ? ' (now signed)' : ''));
        if ($tampered) {
            $this->error("TAMPERED    : {$tampered}  — rows " . implode(', ', $tamperedIds));

            return self::FAILURE;
        }
        $this->info('No tampering detected.');

        return self::SUCCESS;
    }
}
