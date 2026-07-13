<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Services\Links\Geo\GeoLocationResolver;
use App\Services\Links\Geo\GeoLocator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('geo:locate-clicks {--limit=0 : Stop after locating this many clicks (0 = all). Bounds a single run on a huge table.}')]
#[Description('Backfill geolocation for clicks recorded before geo was enabled or before the database was available. A click whose IP was already pruned (ips:prune) stays unlocated. Idempotent and resumable — safe to run repeatedly.')]
class LocateClicks extends Command
{
    private const CHUNK = 500;

    // Generous TTL: a full backfill on a large table can run long. The command
    // is idempotent and resumable, so a lock expiry at worst causes a concurrent
    // run to re-touch the remaining rows (same geo id, no double-count) — the
    // long TTL just makes that unlikely.
    private const LOCK_SECONDS = 21600;

    // Bound the ip->id memo so a full run over a high-cardinality clicks table
    // cannot grow it without limit and OOM the CLI (which would leave the lock
    // held until its TTL). IPs cluster temporally, so a reset at the cap only
    // costs a few repeated dictionary lookups.
    private const MEMO_CAP = 100_000;

    /**
     * @var array<string, int|null> Memo of ip value => geo_location_id for the
     *                              run: the same IPs recur heavily, so this
     *                              collapses per-click lookups and dictionary
     *                              queries to one per distinct IP.
     */
    private array $memo = [];

    public function handle(GeoLocator $geoLocator, GeoLocationResolver $resolver): int
    {
        $lock = Cache::lock('geo:locate-clicks', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another geo:locate-clicks run is in progress; aborting.');

            return self::FAILURE;
        }

        try {
            $located = $this->backfill($geoLocator, $resolver, (int) $this->option('limit'));
        } finally {
            $lock->release();
        }

        $this->info("Located {$located} click(s).");

        return self::SUCCESS;
    }

    private function backfill(GeoLocator $geoLocator, GeoLocationResolver $resolver, int $limit): int
    {
        $located = 0;

        // Keyset by id: updating geo_location_id (the filtered column) is safe —
        // the window advances by id, so rows are neither skipped nor revisited.
        Click::query()
            ->whereNull('geo_location_id')
            ->whereNotNull('ip_address_id')
            ->with('ipAddress:id,value')
            ->chunkById(self::CHUNK, function (Collection $clicks) use ($geoLocator, $resolver, $limit, &$located): bool {
                /** @var Click $click */
                foreach ($clicks as $click) {
                    $geoLocationId = $this->resolveForIp($geoLocator, $resolver, (string) $click->ipAddress?->value);

                    // Unknown or pruned IP: leave the click unlocated.
                    if ($geoLocationId !== null) {
                        $click->update(['geo_location_id' => $geoLocationId]);

                        if (++$located >= $limit && $limit > 0) {
                            return false; // stop the chunk loop at the limit
                        }
                    }
                }

                return true;
            });

        return $located;
    }

    private function resolveForIp(GeoLocator $geoLocator, GeoLocationResolver $resolver, string $ip): ?int
    {
        if (array_key_exists($ip, $this->memo)) {
            return $this->memo[$ip];
        }

        if (count($this->memo) >= self::MEMO_CAP) {
            $this->memo = [];
        }

        return $this->memo[$ip] = $resolver->resolveId($geoLocator->locate($ip));
    }
}
