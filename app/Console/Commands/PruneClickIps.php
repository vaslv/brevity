<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\IpAddress;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('ips:prune')]
#[Description('IP retention (decision 2026-07-11): detach IPs from clicks older than the configured retention and delete orphaned ip_addresses rows.')]
class PruneClickIps extends Command
{
    private const BATCH = 10_000;

    public function handle(): int
    {
        $lock = Cache::lock('ips:prune', 3600);

        if (! $lock->get()) {
            $this->warn('Another ips:prune run is in progress; aborting.');

            return self::FAILURE;
        }

        try {
            $cutoff = now()->subDays((int) config('tracking.ip_retention_days'));
            $detached = 0;

            // Batched UPDATEs: one huge UPDATE over a years-old clicks table
            // would hold locks and bloat WAL; each batch commits on its own
            // (partial progress is fine — the command is idempotent).
            do {
                $affected = Click::query()
                    ->whereNotNull('ip_address_id')
                    ->where('created_at', '<', $cutoff)
                    ->limit(self::BATCH)
                    ->update(['ip_address_id' => null]);

                $detached += $affected;
            } while ($affected > 0);

            // Orphaned dictionary rows carry the actual personal data — the
            // whole point of the retention policy. Known transient race: a
            // concurrent click may have SELECTed this ip id but not yet
            // INSERTed; its FK then fails, the job retries and the resolver
            // recreates the row — no data is lost (accepted, see review).
            $deleted = 0;

            do {
                $affected = IpAddress::query()
                    ->whereNotExists(fn ($query) => $query
                        ->selectRaw('1')
                        ->from('clicks')
                        ->whereColumn('clicks.ip_address_id', 'ip_addresses.id'))
                    ->limit(self::BATCH)
                    ->delete();

                $deleted += $affected;
            } while ($affected > 0);

            $this->info("Detached IPs from {$detached} click(s); deleted {$deleted} orphaned IP row(s).");
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
