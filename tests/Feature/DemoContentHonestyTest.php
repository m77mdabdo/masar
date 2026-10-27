<?php

declare(strict_types=1);

use App\Enums\ArticleSection;
use App\Models\Article;
use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Person;
use App\Support\DemoContent;
use Database\Factories\Support\ArabicContent;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\FlagshipStoriesSeeder;
use Database\Seeders\MarketFiguresSeeder;
use Database\Seeders\SiteStructureSeeder;
use Database\Seeders\Support\ImageCatalogue;

/**
 * Demo content may invent companies, people and figures. It may never put a
 * real authority's name on any of them — the rows sit in a database that later
 * becomes production, and "it was demo data" does not survive a screenshot.
 *
 * Every generated article used to cite the CMA, GASTAT, the central bank or a
 * ministry as its source, and the market seed put invented levels on TASI and
 * invented Saudi unemployment on the front page. These hold the line.
 */

/** Names that must never appear as the source of a generated figure. */
const REAL_BODIES = [
    'هيئة السوق المالية', 'الهيئة العامة للإحصاء', 'وزارة', 'البنك المركزي', 'تداول',
    'المركز الوطني', 'صندوق تنمية', 'السوق المالية السعودية', 'ساما',
];

/** Real instruments and national statistics the sample market rows used to carry. */
const REAL_MARKET_LABELS = ['تاسي', 'نمو', 'برنت', 'الذهب', 'البطالة', 'الناتج غير النفطي', 'الاستثمار الأجنبي', 'السعودية', 'الإمارات', 'مصر'];

/** @return array<int, array<string, mixed>> */
function flagshipStories(): array
{
    $method = new ReflectionMethod(FlagshipStoriesSeeder::class, 'stories');

    return $method->invoke(new FlagshipStoriesSeeder);
}

it('never attributes a generated source or figure to a real body', function (): void {
    $attributions = [...ArabicContent::SOURCE_PUBLISHERS, ...array_column(ArabicContent::KEY_NUMBERS, 'source')];

    foreach ($attributions as $name) {
        foreach (REAL_BODIES as $real) {
            expect($name)->not->toContain($real);
        }

        expect($name)->toContain('تخيّلي');
    }
});

it('gives generated sources URLs on the reserved .example domain', function (): void {
    $article = Article::factory()->withSources()->create();

    foreach ($article->sources as $source) {
        expect(parse_url((string) $source->url, PHP_URL_HOST))->toEndWith('.example');
    }
});

it('puts no real instrument or national statistic in the sample market figures', function (): void {
    $values = (new MarketFiguresSeeder)->values();

    $labels = collect(['market.ticker', 'market.instruments', 'market.pulse', 'market.data'])
        ->flatMap(fn (string $key): array => array_column($values[$key], 'label'))
        ->push($values['market.index']['label']);

    expect($labels)->toHaveCount(18);

    foreach ($labels as $label) {
        expect(in_array($label, REAL_MARKET_LABELS, true))->toBeFalse("real label in sample data: {$label}");
    }
});

it('attributes every flagship figure and source to an invented company, as fictional', function (): void {
    foreach (flagshipStories() as $story) {
        foreach ($story['numbers'] as $number) {
            expect($number['source'])->toContain('تخيّلي');
        }

        foreach ($story['sources'] as $source) {
            expect($source['title'])->toContain('تخيّلي')
                ->and(parse_url($source['url'], PHP_URL_HOST))->toEndWith('.example');
        }

        foreach (REAL_BODIES as $real) {
            expect(json_encode($story, JSON_UNESCAPED_UNICODE))->not->toContain($real);
        }
    }
});

it('writes every flagship article as all four questions, with a photograph that exists', function (): void {
    $catalogue = array_column(ImageCatalogue::entries(), 'file');
    $sections = array_map(fn (ArticleSection $s): string => $s->value, ArticleSection::cases());

    expect(flagshipStories())->toHaveCount(count(FlagshipStoriesSeeder::HEROES));

    foreach (flagshipStories() as $story) {
        expect(array_keys($story['sections']))->toEqualCanonicalizing($sections)
            ->and(count($story['summary']))->toBeBetween(2, 5)
            ->and(trim($story['why']))->not->toBe('')
            ->and($catalogue)->toContain(FlagshipStoriesSeeder::HEROES[$story['slug']]);
    }
});

it('gives no invented company a ticker or a real-looking website', function (): void {
    foreach (DemoContentSeeder::COMPANIES as $company) {
        expect($company['slug'])->toMatch('/^[a-z0-9-]+$/')
            ->not->toMatch('/^company-\d+$/');
    }

    // Exercised through the seeder's own method so the attributes it writes
    // are the ones checked.
    $method = new ReflectionMethod(DemoContentSeeder::class, 'seedCompanies');
    $this->seed([CategorySeeder::class, SiteStructureSeeder::class]);

    $companies = $method->invoke(new DemoContentSeeder, Industry::query()->get()->keyBy('slug'), Country::query()->get());

    foreach ($companies as $company) {
        expect($company->ticker)->toBeNull()
            ->and(parse_url((string) $company->website, PHP_URL_HOST))->toEndWith('.example');
    }
});

it('tells the reader a demo company or person is invented, and only those', function (): void {
    [$fictional, $fictionalPerson] = DemoContent::record(fn (): array => [Company::factory()->create(), Person::factory()->create()]);
    $real = Company::factory()->create();

    $this->get('/ar/companies/'.$fictional->slug)->assertOk()->assertSee('شركة تخيّلية لأغراض العرض', false);
    $this->get('/ar/people/'.$fictionalPerson->slug)->assertOk()->assertSee('شخصية تخيّلية لأغراض العرض', false);
    $this->get('/ar/companies/'.$real->slug)->assertOk()->assertDontSee('شركة تخيّلية', false);
});
