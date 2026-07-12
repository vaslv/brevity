<?php

namespace App\Console\Commands;

use App\Models\Click;
use App\Models\Link;
use App\Models\LinkClickCounter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('clicks:prune {--link= : Code of the link whose clicks are deleted}')]
#[Description('Delete all clicks of one link (GDPR requests, scanner junk — TRK-06). Callbacks cascade; the link\'s counters are reset in the same transaction.')]
class PruneLinkClicks extends Command
{
    public function handle(): int
    {
        $code = (string) $this->option('link');

        if ($code === '') {
            $this->warn('Pass --link=<code>; nothing to do.');

            return self::FAILURE;
        }

        $link = Link::withTrashed()->where('code', $code)->first();

        if ($link === null) {
            $this->error("Link with code \"{$code}\" not found.");

            return self::FAILURE;
        }

        // One transaction: clicks (callbacks cascade via FK) and the counter
        // rows disappear together — the invariant "counters change only in the
        // click transaction or a rebuild" holds. Deliberate trade-off: a link
        // with millions of clicks would hold row locks and bloat WAL for the
        // duration — acceptable for a manual GDPR/cleanup one-off.
        $deleted = DB::transaction(function () use ($link): int {
            $deleted = Click::query()->where('link_id', $link->id)->delete();
            LinkClickCounter::query()->where('link_id', $link->id)->delete();

            return $deleted;
        });

        $this->info("Deleted {$deleted} click(s) of link {$code} (callbacks cascaded, counters reset).");

        return self::SUCCESS;
    }
}
