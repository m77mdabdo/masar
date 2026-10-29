<?php

declare(strict_types=1);

use App\Enums\TranslationStatus;
use App\Models\Article;
use App\Models\HomepageLayout;
use App\Support\EntityUrl;
use App\Support\TranslatableContent;
use Database\Seeders\ContentTranslationsSeeder;

/*
 * The demo content in every edition (owner's directive, 2026-10-29): a
 * complete dictionary publishes a translation marked owner_authorized — never
 * reviewed — and an incomplete one publishes nothing rather than Arabic.
 */
beforeEach(fn () => HomepageLayout::factory()->create(['is_active' => true]));

function englishFor(Article $source, array $drop = []): array
{
    $dict = [];
    foreach (app(TranslatableContent::class)->segments($source) as $path => $text) {
        if (preg_match('/\p{Arabic}/u', $text) && ! in_array($path, $drop, true)) {
            $dict[sha1(trim($text))] = 'EN '.$path;
        }
    }

    return $dict;
}

function originalStory(): Article
{
    $a = publishableArticle(['status' => \App\Enums\ArticleStatus::Published, 'published_at' => now()->subDay(), 'locale' => 'ar']);
    $a->blocks()->create(['type' => 'paragraph', 'data' => ['text' => 'نمت الإيرادات 30% وفق Aramco.'], 'sort_order' => 1]);

    return $a->fresh('blocks');
}

it('publishes a complete translation as owner_authorized, with every segment in the edition', function (): void {
    $source = originalStory();
    (new ContentTranslationsSeeder)->withDictionaries(['en' => englishFor($source)])->run();

    $en = Article::query()->where('translation_group_id', $source->translation_group_id)->where('locale', 'en')->sole();

    expect($en->translation_status)->toBe(TranslationStatus::OwnerAuthorized)
        ->and($en->translation_reviewed_by_id)->toBeNull()
        ->and($en->status)->toBe(\App\Enums\ArticleStatus::Published)
        ->and($en->fact_checked_at?->equalTo($source->fact_checked_at))->toBeTrue()
        ->and(preg_match('/\p{Arabic}/u', implode(' ', app(TranslatableContent::class)->segments($en->fresh('blocks')))))->toBe(0)
        ->and($en->isPublishable())->toBe([]);

    // The original is untouched: every one of its segments is still Arabic.
    $original = app(TranslatableContent::class)->segments($source->fresh(['blocks', 'sources']));
    expect(array_filter($original, fn (string $s): bool => str_starts_with($s, 'EN ')))->toBe([]);

    // Without the owner's ruling, the gate admits only reviewed translations.
    config()->set('masar.translation.owner_authorized_publication', false);
    expect(array_column(app(\App\Actions\Articles\EvaluatePublishGate::class)->detailed($en), 'rule'))->toContain('translation_review');
});

it('publishes nothing for a story whose dictionary is incomplete, and reports it', function (): void {
    $source = originalStory();
    $seeder = (new ContentTranslationsSeeder)->withDictionaries(['fr' => englishFor($source, ['blocks.0.text'])]);
    $seeder->run();

    expect(Article::query()->where('translation_group_id', $source->translation_group_id)->where('locale', 'fr')->exists())->toBeFalse()
        ->and($seeder->missing['fr'] ?? 0)->toBeGreaterThan(0);
});

it('adds nothing on a second run and never replaces a translation that exists', function (): void {
    $source = originalStory();
    $seeder = fn () => (new ContentTranslationsSeeder)->withDictionaries(['en' => englishFor($source)])->run();
    $seeder();
    Article::query()->where('locale', 'en')->update(['title' => 'Edited by a person']);
    $seeder();

    expect(Article::query()->where('translation_group_id', $source->translation_group_id)->where('locale', 'en')->count())->toBe(1)
        ->and(Article::query()->where('locale', 'en')->value('title'))->toBe('Edited by a person');
});

it('serves the translated story with no Arabic outside the brand and the switcher', function (): void {
    $source = originalStory();
    $source->author->forceFill(['name' => 'كاتب', 'name_translations' => ['en' => 'Staff writer']])->save();
    $checker = \App\Models\User::factory()->create(['name' => 'مدقق معلومات', 'name_translations' => ['en' => 'Fact-checker']]);
    // A sponsor is disclosed in the reader's language; the checker's name sits in the source list.
    $source->forceFill(['is_sponsored' => true, 'sponsor_name' => 'مجموعة الأفق القابضة', 'fact_checker_id' => $checker->id])->save();
    $source->category->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Markets']);
    $source = $source->fresh(['blocks', 'sources']);
    (new ContentTranslationsSeeder)->withDictionaries(['en' => englishFor($source)])->run();
    $en = Article::query()->where('locale', 'en')->sole();

    expect($en->sponsor_name)->toBe('EN sponsor_name');

    // The story page, and the category listing whose cards load the byline
    // through a column-limited eager load.
    foreach ([app(EntityUrl::class)->for($en->fresh('category'), 'en'), app(EntityUrl::class)->for($en->category, 'en')] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect(arabicOutsideMarkedElements($html))->toBe([]);

        // Structured data is read by search engines in the page's language too.
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $ld);
        $ldText = json_encode(array_map(fn ($j) => json_decode($j, true), $ld[1]), JSON_UNESCAPED_UNICODE);
        preg_match_all('/"[^"]*":"[^"]*\p{Arabic}[^"]*"/u', $ldText, $arabicFields);
        expect($arabicFields[0])->toBe([]);
    }
});

it('catches an untranslated byline as a leak', function (): void {
    // The detector itself, on a page that does leak.
    $source = originalStory();
    $source->author->forceFill(['name' => 'رائد العرفج', 'name_translations' => null])->save();
    (new ContentTranslationsSeeder)->withDictionaries(['en' => englishFor($source)])->run();
    $en = Article::query()->where('locale', 'en')->sole();

    $html = $this->get(app(EntityUrl::class)->for($en->fresh('category'), 'en'))->getContent();

    expect(implode(' ', arabicOutsideMarkedElements($html)))->toContain('رائد');
});

/**
 * Arabic-script text on a page outside any element whose lang declares it
 * (the switcher's native names, the «مسار» lockup). Scripts, styles and the
 * head are not reader-visible text.
 *
 * @return list<string>
 */
function arabicOutsideMarkedElements(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    foreach (iterator_to_array($xpath->query('//head|//script|//style|//*[@lang="ar" or @lang="ur-PK"]')) as $node) {
        $node->parentNode?->removeChild($node);
    }
    preg_match_all('/\p{Arabic}[\p{Arabic}\s]*/u', (string) $dom->textContent, $m);

    return array_values(array_filter(array_map('trim', $m[0])));
}

it('refuses a byline read from a load that left the translations out', function (): void {
    $user = \App\Models\User::factory()->create(['name' => 'كاتب', 'name_translations' => ['en' => 'Writer']]);
    app()->setLocale('en');

    expect(\App\Models\User::query()->select(['id', 'name', 'name_translations'])->find($user->id)->display_name)->toBe('Writer');
    expect(fn () => \App\Models\User::query()->select(['id', 'name'])->find($user->id)->display_name)
        ->toThrow(\Illuminate\Database\Eloquent\MissingAttributeException::class);
});

it('has a translation in every edition for every string the demo factories can generate', function (): void {
    // The demo stories are drawn at random from fixed pools, so each fresh seed
    // composes different headlines and figures. Covering only the strings one
    // seed happened to draw left 45 of 83 stories untranslated on the next.
    // The closure is finite: every pool entry, every subject × predicate, and
    // every riyal figure the factory can produce.
    $pools = new ReflectionClass(\Database\Factories\Support\ArabicContent::class);
    $halves = [...\Database\Factories\Support\ArabicContent::HEADLINE_SUBJECTS, ...\Database\Factories\Support\ArabicContent::HEADLINE_PREDICATES];
    $strings = [];
    foreach ($pools->getConstants() as $constant) {
        array_walk_recursive($constant, function ($value) use (&$strings, $halves): void {
            if (is_string($value) && preg_match('/\p{Arabic}/u', $value) && ! in_array($value, $halves, true)) {
                $strings[] = trim($value);
            }
        });
    }
    foreach (\Database\Factories\Support\ArabicContent::HEADLINE_SUBJECTS as $subject) {
        foreach (\Database\Factories\Support\ArabicContent::HEADLINE_PREDICATES as $predicate) {
            $strings[] = $subject.' '.$predicate;
        }
    }
    foreach (range(1, 40) as $billions) {
        $strings[] = $billions.' مليار ريال';
    }

    foreach (\App\Support\Locales::codes() as $locale) {
        if ($locale === \App\Support\Locales::default()) {
            continue;
        }
        $dictionary = [];
        foreach (glob(database_path("seeders/data/content/{$locale}*.json")) as $file) {
            $dictionary += json_decode((string) file_get_contents($file), true);
        }
        $missing = array_values(array_filter(array_unique($strings), fn (string $s): bool => blank($dictionary[sha1($s)] ?? null)));

        expect($missing)->toBe([], "{$locale} has no translation for ".count($missing).' generated strings');
    }
});

it('corrects imported machine text only where it is still the machine’s, never a person’s edit', function (): void {
    $source = originalStory();
    $source->forceFill(['subtitle' => 'نمو 40 مليار ريال'])->save();
    $source = $source->fresh(['blocks', 'sources']);
    $dict = englishFor($source);
    $dict[sha1('نمو 40 مليار ريال')] = '增长 40 十亿里亚尔';
    (new ContentTranslationsSeeder)->withDictionaries(['zh-hans' => $dict])->run();
    $zh = Article::query()->where('locale', 'zh-hans')->sole();
    expect($zh->subtitle)->toBe('增长 40 十亿里亚尔');

    // A person edits the title; the correction must reach only the untouched subtitle.
    $zh->forceFill(['title' => 'Edited by a person'])->save();
    $fixes = [sha1('增长 40 十亿里亚尔') => '增长 400亿里亚尔', sha1('EN title') => 'should not land'];
    (new ContentTranslationsSeeder)->withDictionaries(['zh-hans' => $dict])->withCorrections(['zh-hans' => $fixes])->run();

    $zh = $zh->fresh();
    expect($zh->subtitle)->toBe('增长 400亿里亚尔')
        ->and($zh->title)->toBe('Edited by a person')
        ->and($source->fresh()->subtitle)->toBe('نمو 40 مليار ريال');
});
