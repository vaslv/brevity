<?php

namespace Database\Seeders;

use App\Models\Condition;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use App\Models\User;
use App\Models\UserAgent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DatabaseSeeder is disabled in production.');

            return;
        }

        $this->users();
        $services = $this->services();
        $domains = $this->domains();
        $this->domainGroups($domains);
        $urls = $this->urls();
        $conditions = $this->conditions();
        $links = $this->links($services, $domains);
        $this->rules($links, $urls, $conditions);
        $dictionaries = $this->clickDictionaries();
        $clicks = $this->clicks($links, $dictionaries);
        $this->callbacks($clicks, $links);
    }

    /**
     * @param  array<int, array<string, mixed>>  $clicks
     * @param  array<int, Link>  $links
     */
    private function callbacks(array $clicks, array $links): void
    {
        $linksById = collect($links)->keyBy('id');
        $statuses = ['pending', 'sent', 'sent', 'sent', 'failed'];
        $sample = array_slice($clicks, 0, (int) (count($clicks) * 0.25));
        $rows = [];

        foreach ($sample as $click) {
            $link = $linksById[$click['link_id']] ?? null;
            if ($link === null || $link->callback_data === null) {
                continue;
            }

            $status = $statuses[array_rand($statuses)];
            $createdAt = CarbonImmutable::parse($click['created_at']);

            $rows[] = [
                'service_id' => $click['service_id'],
                'click_id' => $click['id'],
                'data' => json_encode($link->callback_data),
                'status' => $status,
                'attempts' => match ($status) {
                    'pending' => 0,
                    'sent' => 1,
                    'failed' => 5,
                },
                'response_code' => match ($status) {
                    'sent' => 200,
                    'failed' => 500,
                    default => null,
                },
                'response_body' => match ($status) {
                    'sent' => '{"ok":true}',
                    'failed' => 'Internal Server Error',
                    default => null,
                },
                'last_attempt_at' => $status === 'pending' ? null : $createdAt->addMinute(),
                'created_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('callbacks')->insert($chunk);
        }
    }

    /**
     * @return array{referrers: array<int, Referrer>, userAgents: array<int, UserAgent>, ipAddresses: array<int, IpAddress>}
     */
    private function clickDictionaries(): array
    {
        $referrers = collect([
            'https://t.me/channel',
            'https://vk.com/wall',
            'https://twitter.com/share',
            'https://www.google.com/',
            'https://yandex.ru/',
            'https://news.ycombinator.com/',
        ])->map(fn (string $v) => Referrer::firstOrCreate(['value' => $v]))->all();

        $userAgents = collect([
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ])->map(fn (string $v) => UserAgent::firstOrCreate(['value' => $v]))->all();

        $ipAddresses = collect([
            '203.0.113.10',
            '203.0.113.45',
            '198.51.100.7',
            '198.51.100.88',
            '192.0.2.15',
            '192.0.2.201',
        ])->map(fn (string $v) => IpAddress::firstOrCreate(['value' => $v]))->all();

        return compact('referrers', 'userAgents', 'ipAddresses');
    }

    /**
     * @param  array<int, Link>  $links
     * @param  array{referrers: array<int, Referrer>, userAgents: array<int, UserAgent>, ipAddresses: array<int, IpAddress>}  $dict
     * @return array<int, array<string, mixed>>
     */
    private function clicks(array $links, array $dict): array
    {
        $now = CarbonImmutable::now();
        $rows = [];

        foreach (range(0, 13) as $daysAgo) {
            // more recent days are heavier
            $perDay = random_int(20, 60) + max(0, (14 - $daysAgo) * 2);

            for ($i = 0; $i < $perDay; $i++) {
                $link = $links[array_rand($links)];
                $at = $now->subDays($daysAgo)
                    ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

                $rows[] = [
                    'service_id' => $link->service_id,
                    'link_id' => $link->id,
                    'url_id' => $link->rules()->orderBy('priority')->value('url_id')
                        ?? throw new \RuntimeException('Link has no rules'),
                    'referrer_id' => $dict['referrers'][array_rand($dict['referrers'])]->id,
                    'user_agent_id' => $dict['userAgents'][array_rand($dict['userAgents'])]->id,
                    'ip_address_id' => $dict['ipAddresses'][array_rand($dict['ipAddresses'])]->id,
                    'created_at' => $at,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('clicks')->insert($chunk);
        }

        return DB::table('clicks')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * @return array<string, Condition>
     */
    private function conditions(): array
    {
        $now = CarbonImmutable::now();

        $rows = [
            'past' => $now->subDays(7),
            'soon' => $now->addDays(3),
            'far' => $now->addDays(30),
        ];

        return collect($rows)
            ->mapWithKeys(fn (CarbonImmutable $at, string $key) => [
                $key => Condition::create([
                    'type' => 'time_before',
                    'data' => ['before' => $at->format('Y-m-d\TH:i:sP')],
                ]),
            ])
            ->all();
    }

    /**
     * @param  array<string, Domain>  $domains
     */
    private function domainGroups(array $domains): void
    {
        // brv.example is intentionally shared across both groups to exercise the
        // many-to-many relationship (a domain may belong to several groups).
        $groups = [
            'Primary' => ['go.example', 'brv.example'],
            'Campaigns' => ['brv.example', 'lnk.example'],
        ];

        foreach ($groups as $name => $domainKeys) {
            $group = DomainGroup::firstOrCreate(['name' => $name], ['code' => Str::slug($name)]);

            $ids = collect($domainKeys)
                ->map(fn (string $key): int => $domains[$key]->id)
                ->all();

            $group->domains()->syncWithoutDetaching($ids);
        }
    }

    /**
     * @return array<string, Domain>
     */
    private function domains(): array
    {
        $values = ['go.example', 'brv.example', 'lnk.example'];

        return collect($values)
            ->mapWithKeys(fn (string $value) => [
                $value => Domain::firstOrCreate(['value' => $value]),
            ])
            ->all();
    }

    /**
     * @param  array<string, Service>  $services
     * @param  array<string, Domain>  $domains
     * @return array<int, Link>
     */
    private function links(array $services, array $domains): array
    {
        $rows = [
            ['marketing', 'go.example', 'Spring campaign', true,  ['source' => 'marketing', 'click_id' => '{{click.id}}']],
            ['marketing', 'go.example', 'Webinar invite',  true,  ['source' => 'webinar',  'link_id' => '{{link.id}}']],
            ['marketing', 'brv.example', 'Press kit',       false, null],
            ['marketing', 'brv.example', 'Holiday promo',   true,  null],
            ['marketing', 'lnk.example', null,              false, null],
            ['sales',     'go.example', 'Demo booking',    true,  ['lead' => 'demo']],
            ['sales',     'brv.example', 'Proposal — Acme', false, null],
            ['sales',     'brv.example', 'Proposal — Globex', false, null],
            ['sales',     'lnk.example', 'Q2 pricing',      false, null],
            ['sales',     'lnk.example', null,              true,  null],
            ['support',   'go.example', 'Onboarding docs', false, null],
            ['support',   'go.example', 'Status page',     false, null],
            ['support',   'brv.example', 'Download',        true,  null],
            ['support',   'lnk.example', 'Contact form',    false, null],
            ['support',   'lnk.example', null,              false, null],
        ];

        $links = [];

        foreach ($rows as [$serviceKey, $domainKey, $title, $forward, $callbackData]) {
            $links[] = Link::create([
                'service_id' => $services[$serviceKey]->id,
                'domain_id' => $domains[$domainKey]->id,
                'title' => $title,
                'forward_query' => $forward,
                'callback_data' => $callbackData,
            ]);
        }

        return $links;
    }

    /**
     * @param  array<int, Link>  $links
     * @param  array<int, Url>  $urls
     * @param  array<string, Condition>  $conditions
     */
    private function rules(array $links, array $urls, array $conditions): void
    {
        $modes = ['direct', 'delayed', 'manual'];

        foreach ($links as $index => $link) {
            $primaryUrl = $urls[$index % count($urls)];

            Rule::create([
                'link_id' => $link->id,
                'url_id' => $primaryUrl->id,
                'transition_mode' => $modes[$index % 3],
                'priority' => 10,
            ]);

            if ($index % 3 === 0) {
                $fallback = $urls[($index + 1) % count($urls)];
                $condition = $conditions[['past', 'soon', 'far'][$index % 3]];

                $conditionalRule = Rule::create([
                    'link_id' => $link->id,
                    'url_id' => $fallback->id,
                    'transition_mode' => 'direct',
                    'priority' => 1,
                ]);
                $conditionalRule->conditions()->attach($condition->id);
            }
        }
    }

    /**
     * @return array<string, Service>
     */
    private function services(): array
    {
        $rows = [
            'marketing' => ['name' => 'Marketing', 'callback_url' => 'https://hooks.example.com/marketing'],
            'sales' => ['name' => 'Sales', 'callback_url' => 'https://hooks.example.com/sales'],
            'support' => ['name' => 'Support', 'callback_url' => null],
        ];

        return collect($rows)
            ->mapWithKeys(fn (array $attrs, string $key) => [
                $key => Service::firstOrCreate(['name' => $attrs['name']], $attrs),
            ])
            ->all();
    }

    /**
     * @return array<int, Url>
     */
    private function urls(): array
    {
        $values = [
            'https://example.com/products',
            'https://example.com/pricing',
            'https://example.com/blog/launch-announcement',
            'https://example.com/cases/acme',
            'https://example.com/webinar/2026-spring',
            'https://example.com/contact',
            'https://example.com/docs/getting-started',
            'https://example.com/download',
            'https://example.com/careers',
        ];

        return collect($values)
            ->map(fn (string $value) => Url::firstOrCreate(['value' => $value]))
            ->all();
    }

    /**
     * @return array<int, User>
     */
    private function users(): array
    {
        return [
            User::firstOrCreate(
                ['email' => 'admin@example.com'],
                ['name' => 'Admin', 'password' => Hash::make('password')],
            ),
            User::firstOrCreate(
                ['email' => 'editor@example.com'],
                ['name' => 'Editor', 'password' => Hash::make('password')],
            ),
        ];
    }
}
