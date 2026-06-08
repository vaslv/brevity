<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:sync')]
#[Description('Create a Domain row for each short-link host in APP_HOST (every host except the technical one). Idempotent — runs on deploy.')]
class SyncDomainsFromEnv extends Command
{
    public function handle(): int
    {
        $technicalHost = config('app.technical_host');

        $shortLinkHosts = collect(config('app.hosts'))
            ->reject(fn (string $host): bool => $technicalHost !== null && strcasecmp($host, (string) $technicalHost) === 0)
            ->values();

        $created = 0;

        foreach ($shortLinkHosts as $host) {
            // Match case-insensitively so a manually added domain isn't
            // duplicated, but store the host verbatim from APP_HOST.
            $exists = Domain::query()
                ->whereRaw('lower(value) = ?', [strtolower($host)])
                ->exists();

            if ($exists) {
                continue;
            }

            Domain::query()->create(['value' => $host]);
            $created++;
        }

        $this->info("Domains synced from APP_HOST: {$created} created, ".($shortLinkHosts->count() - $created).' already present.');

        return self::SUCCESS;
    }
}
