<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DomainGroups\Pages\CreateDomainGroup;
use App\Filament\Resources\DomainGroups\Pages\ViewDomainGroup;
use App\Filament\Resources\DomainGroups\RelationManagers\DomainsRelationManager;
use App\Filament\Resources\Domains\Pages\CreateDomain;
use App\Filament\Resources\Domains\Pages\ListDomains;
use App\Filament\Resources\Domains\Pages\ViewDomain;
use App\Filament\Resources\Links\Pages\CreateLink;
use App\Filament\Resources\Links\Pages\ListLinks;
use App\Filament\Resources\Links\Pages\ViewLink;
use App\Filament\Widgets\LinksPerDomainChart;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\Link;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class DomainIdnTest extends TestCase
{
    use RefreshDatabase;

    private const string ASCII = 'xn--e1afmkfd.xn--p1ai';

    public static function domainInputs(): array
    {
        return [['пример.рф'], [self::ASCII], [' ПРИМЕР.РФ. ']];
    }

    public function test_cards_and_chart_show_unicode_with_technical_copy_values(): void
    {
        $domain = Domain::factory()->create(['value' => self::ASCII]);
        $link = Link::factory()->forDomain($domain)->create();

        Livewire::test(ViewDomain::class, ['record' => $domain->id])
            ->assertSee('пример.рф')->assertSee(self::ASCII)->assertSee('https://'.self::ASCII);
        Livewire::test(ViewLink::class, ['record' => $link->id])->assertSee('пример.рф');
        $chartData = (new ReflectionMethod(LinksPerDomainChart::class, 'getData'))->invoke(new LinksPerDomainChart);
        $this->assertSame(['пример.рф'], $chartData['labels']);
    }

    #[DataProvider('domainInputs')]
    public function test_creates_ascii_domain_from_either_form(string $input): void
    {
        Livewire::test(CreateDomain::class)
            ->fillForm(['value' => $input])->call('create')->assertHasNoFormErrors();
        $this->assertSame(self::ASCII, Domain::query()->sole()->value);
    }

    #[DataProvider('domainInputs')]
    public function test_duplicate_forms_fail_validation_before_save(string $input): void
    {
        Domain::factory()->create(['value' => self::ASCII]);
        Livewire::test(CreateDomain::class)
            ->fillForm(['value' => $input])->call('create')->assertHasFormErrors(['value' => 'unique']);
        $this->assertSame(1, Domain::query()->count());
    }

    public function test_invalid_domains_fail_with_a_readable_error(): void
    {
        foreach (['https://пример.рф/path', 'xn--a.test', 'пример.рф:443', str_repeat('я', 60).'.рф'] as $input) {
            Livewire::test(CreateDomain::class)
                ->fillForm(['value' => $input])->call('create')
                ->assertHasFormErrors(['value'])
                ->assertSee('Enter a valid domain without a protocol, port or path.');
        }
        $this->assertSame(0, Domain::query()->count());
    }

    public function test_selectors_search_and_label_domains_in_unicode(): void
    {
        $domain = Domain::factory()->create(['value' => self::ASCII]);
        Domain::factory()->create();

        foreach ([CreateLink::class => 'domain_id', CreateDomainGroup::class => 'domains'] as $page => $field) {
            Livewire::test($page)->assertFormFieldExists($field, function (Select $select) use ($domain): bool {
                $this->assertSame('пример.рф', $select->getOptions()[$domain->id]);
                foreach (['пример.рф', self::ASCII] as $search) {
                    $this->assertSame([$domain->id => 'пример.рф'], $select->getSearchResults($search));
                }

                return true;
            });
        }
    }

    public function test_tables_search_both_forms_and_display_unicode(): void
    {
        $domain = Domain::factory()->create(['value' => self::ASCII]);
        $other = Domain::factory()->create();
        $link = Link::factory()->forDomain($domain)->create();
        $otherLink = Link::factory()->forDomain($other)->create();
        $group = DomainGroup::factory()->create();
        $group->domains()->attach([$domain->id, $other->id]);

        foreach (['пример.рф', self::ASCII] as $search) {
            Livewire::test(ListDomains::class)->searchTable($search)
                ->assertCanSeeTableRecords([$domain])->assertCanNotSeeTableRecords([$other])
                ->assertSee('пример.рф');
            Livewire::test(ListLinks::class)->toggleAllTableColumns()->searchTable($search)
                ->assertCanSeeTableRecords([$link])->assertCanNotSeeTableRecords([$otherLink])
                ->assertSee('пример.рф');
            Livewire::test(DomainsRelationManager::class, [
                'ownerRecord' => $group,
                'pageClass' => ViewDomainGroup::class,
            ])->searchTable($search)
                ->assertCanSeeTableRecords([$domain])->assertCanNotSeeTableRecords([$other])
                ->assertSee('пример.рф');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');
        $this->app->setLocale('en');
    }
}
