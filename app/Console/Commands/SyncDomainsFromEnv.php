<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\Links\Domains\DomainName;
use App\Support\HttpHost;
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
        $technicalHost = $technicalHost !== null ? HttpHost::normalize((string) $technicalHost) : null;

        $shortLinkHosts = collect(config('app.hosts'))
            ->map(fn (string $host): string => HttpHost::normalize($host))
            ->reject(fn (string $host): bool => $host === $technicalHost)
            ->map(fn (string $host): string => DomainName::toAscii($host))
            ->unique()
            ->values();

        $created = 0;

        foreach ($shortLinkHosts as $host) {
            $exists = Domain::query()
                ->where('value', $host)
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
