<?php

namespace App\Console\Commands;

use App\Models\Link;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

#[Signature('links:prune-dead {--expired : Prune links past their valid_until} {--exhausted : Prune links at their max_clicks} {--dry-run : Count only, delete nothing}')]
#[Description('Soft-delete dead links. Conditions combine with OR; with no condition flags the command does nothing (safety).')]
class PruneDeadLinks extends Command
{
    public function handle(): int
    {
        if (! $this->option('expired') && ! $this->option('exhausted')) {
            $this->warn('No condition flags given (--expired / --exhausted); nothing to do.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('links:prune-dead', 600);

        if (! $lock->get()) {
            $this->warn('Another links:prune-dead run is in progress; aborting.');

            return self::FAILURE;
        }

        try {
            $query = $this->deadLinksQuery();
            $count = (clone $query)->count();

            if ($this->option('dry-run')) {
                $this->info("[dry-run] {$count} link(s) would be pruned.");

                return self::SUCCESS;
            }

            // Soft delete via the query builder: one UPDATE, no per-model events
            // (the `created` code hook is irrelevant here).
            $query->delete();

            $this->info("Pruned {$count} dead link(s).");
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<Link>
     */
    private function deadLinksQuery(): Builder
    {
        return Link::query()->where(function (Builder $query): void {
            if ($this->option('expired')) {
                $query->orWhere(fn (Builder $q) => $q->whereNotNull('valid_until')->where('valid_until', '<', now()));
            }

            if ($this->option('exhausted')) {
                $query->orWhere(fn (Builder $q) => $q
                    ->whereNotNull('max_clicks')
                    ->whereRaw('(select coalesce(sum(count), 0) from link_click_counters where link_id = links.id) >= links.max_clicks'));
            }
        });
    }
}
