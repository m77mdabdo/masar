<?php

declare(strict_types=1);

use App\Enums\OpportunityPotential;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;

function liveOpportunity(array $attributes = []): Opportunity
{
    static $n = 0;
    $n++;

    // The factory draws titles from a fixed template list, so two fixtures can
    // share one — and an assertion that a title is absent would then fail for
    // the wrong reason.
    return Opportunity::factory()->create([
        'locale' => 'ar',
        'published_at' => now()->subDay(),
        'status' => 'open',
        'title' => "فرصة اختبارية رقم {$n}",
        ...$attributes,
    ]);
}

it('filters by sector in SQL', function (): void {
    $tech = Industry::factory()->create(['slug' => 'fintech']);
    $energy = Industry::factory()->create(['slug' => 'energy']);

    $match = liveOpportunity(['industry_id' => $tech->id]);
    $other = liveOpportunity(['industry_id' => $energy->id]);

    $html = $this->get('/ar/opportunities?sector=fintech')->assertOk()->getContent();

    expect($html)->toContain($match->title)->not->toContain($other->title);
});

it('filters by region', function (): void {
    $saudi = Country::factory()->create(['slug' => 'saudi-arabia']);
    $uae = Country::factory()->create(['slug' => 'uae']);

    $match = liveOpportunity(['country_id' => $saudi->id]);
    $other = liveOpportunity(['country_id' => $uae->id]);

    $html = $this->get('/ar/opportunities?region=saudi-arabia')->assertOk()->getContent();

    expect($html)->toContain($match->title)->not->toContain($other->title);
});

it('filters by potential', function (): void {
    $high = liveOpportunity(['potential' => OpportunityPotential::High]);
    $low = liveOpportunity(['potential' => OpportunityPotential::Low]);

    $html = $this->get('/ar/opportunities?potential=high')->assertOk()->getContent();

    expect($html)->toContain($high->title)->not->toContain($low->title);
});

it('filters by deadline window', function (): void {
    $soon = liveOpportunity(['deadline' => now()->addDays(5)]);
    $later = liveOpportunity(['deadline' => now()->addDays(200)]);

    $html = $this->get('/ar/opportunities?deadline=7')->assertOk()->getContent();

    expect($html)->toContain($soon->title)->not->toContain($later->title);
});

it('filters to open opportunities only', function (): void {
    $open = liveOpportunity(['status' => 'open']);
    $closed = liveOpportunity(['status' => 'closed']);

    $html = $this->get('/ar/opportunities?open_only=1')->assertOk()->getContent();

    expect($html)->toContain($open->title)->not->toContain($closed->title);
});

it('composes several filters together', function (): void {
    $sector = Industry::factory()->create(['slug' => 'mining']);
    $region = Country::factory()->create(['slug' => 'saudi-arabia']);

    $match = liveOpportunity([
        'industry_id' => $sector->id,
        'country_id' => $region->id,
        'potential' => OpportunityPotential::High,
    ]);

    $wrongPotential = liveOpportunity([
        'industry_id' => $sector->id,
        'country_id' => $region->id,
        'potential' => OpportunityPotential::Low,
    ]);

    $html = $this->get('/ar/opportunities?sector=mining&region=saudi-arabia&potential=high')
        ->assertOk()->getContent();

    expect($html)->toContain($match->title)->not->toContain($wrongPotential->title);
});

it('paginates the filtered set, not the whole table', function (): void {
    $sector = Industry::factory()->create(['slug' => 'fintech']);

    Opportunity::factory()->count(20)->create([
        'locale' => 'ar', 'published_at' => now()->subDay(), 'status' => 'open',
    ]);
    liveOpportunity(['industry_id' => $sector->id]);

    // Filtering in PHP after pagination would page over the wrong set and show
    // an empty page two.
    $html = $this->get('/ar/opportunities?sector=fintech')->assertOk()->getContent();

    expect($html)->toContain('1 فرصة');
});

it('keeps filters across pagination links', function (): void {
    $sector = Industry::factory()->create(['slug' => 'fintech']);

    Opportunity::factory()->count(15)->create([
        'locale' => 'ar', 'published_at' => now()->subDay(),
        'status' => 'open', 'industry_id' => $sector->id,
    ]);

    $html = $this->get('/ar/opportunities?sector=fintech')->assertOk()->getContent();

    expect($html)->toContain('sector=fintech');
});

it('does not run a query per rendered opportunity', function (): void {
    $sector = Industry::factory()->create(['slug' => 'fintech']);
    $region = Country::factory()->create(['slug' => 'saudi-arabia']);

    Opportunity::factory()->count(4)->create([
        'locale' => 'ar', 'published_at' => now()->subDay(), 'status' => 'open',
        'industry_id' => $sector->id, 'country_id' => $region->id,
    ]);

    $this->get('/ar/opportunities');

    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $this->get('/ar/opportunities')->assertOk();
    $few = $count;

    Opportunity::factory()->count(8)->create([
        'locale' => 'ar', 'published_at' => now()->subDay(), 'status' => 'open',
        'industry_id' => $sector->id, 'country_id' => $region->id,
    ]);

    $count = 0;
    $this->get('/ar/opportunities')->assertOk();

    // Each card renders its industry and country; without eager loading this
    // would grow by two queries per row.
    expect($count)->toBe($few);
});

it('only offers filter options that have results', function (): void {
    $used = Industry::factory()->create(['slug' => 'used-sector']);
    $unused = Industry::factory()->create(['slug' => 'unused-sector']);

    liveOpportunity(['industry_id' => $used->id]);

    $html = $this->get('/ar/opportunities')->assertOk()->getContent();

    // An option that leads to an empty page is a dead end.
    expect($html)->toContain('used-sector')->not->toContain('unused-sector');
});

it('excludes unpublished opportunities', function (): void {
    $draft = Opportunity::factory()->create(['locale' => 'ar', 'published_at' => null]);

    expect($this->get('/ar/opportunities')->getContent())->not->toContain($draft->title);
});
