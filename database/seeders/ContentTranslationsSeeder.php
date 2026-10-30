<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Translation\CreateTranslationDraft;
use App\Enums\ArticleStatus;
use App\Enums\TranslationStatus;
use App\Models\Article;
use App\Support\Locales;
use App\Support\Settings;
use App\Support\TranslatableContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The demo content in every edition (owner's directive, 2026-10-29).
 *
 * Reads database/seeders/data/content/{locale}.json — machine-assisted
 * translations keyed by sha1 of the Arabic text — and fills, additively:
 * a published translation of every published original article, translated
 * names and descriptions of every entity, opportunities, image alt text,
 * bylines and the market wording. Nothing that exists is overwritten.
 *
 * Translated articles enter as `owner_authorized`, never `reviewed`: no
 * person has read them against the source. Fact-checking is the source's,
 * because the facts are the source's. A string missing from a dictionary is
 * never replaced by its Arabic: the record is skipped and reported, and
 * `masar:translations:coverage` names it.
 */
class ContentTranslationsSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private array $dict = [];

    /** @var array<string, int> */
    public array $missing = [];

    /** @var array<string, array<string, string>> locale => sha1(old machine text) => corrected text */
    private array $corrections = [];

    /** @param array<string, array<string, string>> $corrections */
    public function withCorrections(array $corrections): static
    {
        $this->corrections = $corrections;

        return $this;
    }

    /** @param array<string, array<string, string>> $dictionaries locale => sha1(ar) => text */
    public function withDictionaries(array $dictionaries): static
    {
        $this->dict = $dictionaries;

        return $this;
    }

    public function run(): void
    {
        foreach ($this->dict === [] ? Locales::codes() : [] as $locale) {
            // {locale}.json plus any {locale}-*.json supplement.
            foreach (array_merge(glob(database_path("seeders/data/content/{$locale}.json")) ?: [], glob(database_path("seeders/data/content/{$locale}-*.json")) ?: []) as $file) {
                if ($locale === Locales::default() || ! is_file($file)) {
                    continue;
                }
                $entries = (array) json_decode((string) file_get_contents($file), true);
                if (str_ends_with($file, '-corrections.json')) {
                    $this->corrections[$locale] = $entries;
                } else {
                    $this->dict[$locale] = array_merge($this->dict[$locale] ?? [], $entries);
                }
            }
        }

        foreach (array_keys($this->dict) as $locale) {
            $this->articles($locale);
            $this->taxonomy($locale);
            $this->opportunities($locale);
            $this->media($locale);
            $this->bylines($locale);
            $this->market($locale);
            $this->corrections($locale);
            $this->mentions($locale);
        }

        foreach ($this->missing as $locale => $n) {
            $this->command?->warn("{$locale}: {$n} strings had no translation; their records were skipped.");
        }
    }

    private function t(string $locale, ?string $ar): ?string
    {
        $ar = trim((string) $ar);
        if ($ar === '' || ! preg_match('/\p{Arabic}/u', $ar)) {
            return $ar === '' ? null : $ar;
        }

        $out = $this->dict[$locale][sha1($ar)] ?? null;
        if ($out === null || trim($out) === '') {
            $this->missing[$locale] = ($this->missing[$locale] ?? 0) + 1;

            return null;
        }

        return $out;
    }

    private function articles(string $locale): void
    {
        $content = app(TranslatableContent::class);
        $create = app(CreateTranslationDraft::class);

        $sources = Article::query()->published()->where('locale', Locales::default())->whereNull('source_article_id')
            ->with(['blocks', 'sources', 'author'])->get();

        foreach ($sources as $source) {
            $existing = Article::withTrashed()->where('translation_group_id', $source->translation_group_id)->where('locale', $locale)->first();
            if ($existing !== null) {
                $this->untranslatedCopies($existing, $source, $locale);

                continue;
            }

            $segments = [];
            foreach ($content->segments($source) as $path => $text) {
                $translated = $this->t($locale, $text);
                if ($translated === null) {
                    continue 2; // incomplete: no half-translated story is published
                }
                $segments[$path] = $translated;
            }

            DB::transaction(function () use ($create, $content, $source, $locale, $segments): void {
                $draft = $create->draft($source, $locale, $source->author);
                $draft->load(['blocks', 'sources']);
                $content->apply($draft, $segments);

                $draft->forceFill([
                    'status' => ArticleStatus::Published,
                    'published_at' => $source->published_at,
                    'updated_content_at' => $source->updated_content_at,
                    'fact_checked_at' => $source->fact_checked_at,
                    'fact_checker_id' => $source->fact_checker_id,
                    'editor_id' => $source->editor_id,
                    'is_featured' => $source->is_featured,
                    'translation_status' => TranslationStatus::OwnerAuthorized,
                    'translation_origin' => 'machine-assisted',
                    'translated_at' => now(),
                    'source_hash' => $content->hash($source),
                ])->save();
            });
        }
    }

    /**
     * A column added to the segments after an edition was imported was copied
     * across by replicate() in Arabic. Only a value still identical to the
     * source is filled: identical Arabic in another edition is by definition
     * untranslated, so no editor's wording is ever replaced.
     */
    private function untranslatedCopies(Article $existing, Article $source, string $locale): void
    {
        if ($existing->translation_origin !== 'machine-assisted') {
            return;
        }

        $content = app(TranslatableContent::class);
        $changed = false;
        foreach (['sponsor_name'] as $field) {
            if (filled($source->{$field}) && $existing->{$field} === $source->{$field}) {
                $translated = $this->t($locale, $source->{$field});
                if ($translated !== null) {
                    $existing->{$field} = $translated;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $existing->source_hash = $content->hash($source);
            $existing->save();
        }
    }

    private function taxonomy(string $locale): void
    {
        $tables = [
            'company_translations' => ['company_id', ['name', 'short_description', 'description']],
            'person_translations' => ['person_id', ['name', 'title', 'bio']],
            'topic_translations' => ['topic_id', ['name', 'description']],
            'category_translations' => ['category_id', ['name', 'description', 'meta_title', 'meta_description']],
            'industry_translations' => ['industry_id', ['name']],
            'country_translations' => ['country_id', ['name']],
            'market_translations' => ['market_id', ['name']],
        ];

        foreach ($tables as $table => [$key, $cols]) {
            foreach (DB::table($table)->where('locale', Locales::default())->get() as $ar) {
                $existing = DB::table($table)->where($key, $ar->{$key})->where('locale', $locale)->first();
                $values = [];
                foreach ($cols as $col) {
                    if ($existing !== null && filled($existing->{$col} ?? null)) {
                        continue; // a person's label is never replaced
                    }
                    $translated = $this->t($locale, $ar->{$col} ?? null);
                    if ($translated !== null) {
                        $values[$col] = $translated;
                    }
                }

                if ($values === []) {
                    continue;
                }

                $existing === null
                    ? DB::table($table)->insert([$key => $ar->{$key}, 'locale' => $locale] + $values)
                    : DB::table($table)->where('id', $existing->id)->update($values);
            }
        }
    }

    private function opportunities(string $locale): void
    {
        foreach (DB::table('opportunities')->where('locale', Locales::default())->get() as $ar) {
            if (DB::table('opportunities')->where('translation_group_id', $ar->translation_group_id)->where('locale', $locale)->exists()) {
                continue;
            }

            $title = $this->t($locale, $ar->title);
            $summary = $ar->summary === null ? null : $this->t($locale, $ar->summary);
            $requirements = $ar->requirements === null ? null : array_map(fn ($r) => is_string($r) ? $this->t($locale, $r) : $r, (array) json_decode((string) $ar->requirements, true));

            if ($title === null || ($ar->summary !== null && $summary === null) || in_array(null, (array) $requirements, true)) {
                continue;
            }

            $row = (array) $ar;
            unset($row['id']);
            DB::table('opportunities')->insert(array_merge($row, [
                'locale' => $locale, 'title' => $title, 'summary' => $summary,
                'requirements' => $requirements === null ? null : json_encode($requirements, JSON_UNESCAPED_UNICODE),
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    private function media(string $locale): void
    {
        foreach (DB::table('media')->get(['id', 'custom_properties']) as $m) {
            $props = (array) json_decode((string) $m->custom_properties, true);
            $changed = false;
            foreach (['alt' => 'alt_translations', 'credit' => 'credit_translations'] as $field => $mapKey) {
                if (! isset($props[$field]) || isset($props[$mapKey][$locale]) || ! preg_match('/\p{Arabic}/u', (string) $props[$field])) {
                    continue;
                }
                if (($translated = $this->t($locale, $props[$field])) !== null) {
                    $props[$mapKey][$locale] = $translated;
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('media')->where('id', $m->id)->update(['custom_properties' => json_encode($props, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function bylines(string $locale): void
    {
        foreach (DB::table('users')->get(['id', 'name', 'name_translations']) as $u) {
            $map = (array) json_decode((string) $u->name_translations, true);
            if (isset($map[$locale]) || ($translated = $this->t($locale, $u->name)) === null || $translated === $u->name) {
                continue;
            }
            $map[$locale] = $translated;
            DB::table('users')->where('id', $u->id)->update(['name_translations' => json_encode($map, JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function market(string $locale): void
    {
        $settings = app(Settings::class);
        $map = (array) $settings->forLocale('market.translations', Locales::default());

        $phrases = [(string) $settings->get('market.source'), (string) (((array) $settings->get('market.index'))['label'] ?? '')];
        foreach (['market.ticker', 'market.instruments', 'market.pulse', 'market.data'] as $key) {
            foreach ((array) $settings->get($key) as $row) {
                $phrases[] = (string) ($row['label'] ?? '');
                $phrases[] = (string) ($row['value'] ?? '');
            }
        }

        foreach (array_unique($phrases) as $ar) {
            if ($ar === '' || isset($map[$ar][$locale]) || ! preg_match('/\p{Arabic}/u', $ar)) {
                continue;
            }
            if (($translated = $this->t($locale, $ar)) !== null) {
                $map[$ar][$locale] = $translated;
            }
        }

        $settings->set('market.translations', $map);
    }

    /**
     * A correction to the machine-assisted text already imported — Chinese
     * amounts regrouped from 十亿/百万 into 亿/万, for one. A stored segment is
     * replaced only while it is still exactly the old machine output, so a
     * person's edit to that sentence is never overwritten.
     */
    private function corrections(string $locale): void
    {
        $fixes = $this->corrections[$locale] ?? [];
        if ($fixes === []) {
            return;
        }
        $content = app(TranslatableContent::class);

        $stories = Article::query()->where('locale', $locale)->where('translation_origin', 'machine-assisted')->with(['blocks', 'sources'])->get();
        foreach ($stories as $story) {
            $segments = [];
            foreach ($content->segments($story) as $path => $text) {
                if (isset($fixes[sha1(trim($text))])) {
                    $segments[$path] = $fixes[sha1(trim($text))];
                }
            }
            if ($segments !== []) {
                $content->apply($story, $segments);
                $story->save();
            }
        }

        $settings = app(Settings::class);
        $map = (array) $settings->forLocale('market.translations', Locales::default());
        $changed = false;
        foreach ($map as $ar => $editions) {
            $current = (string) ($editions[$locale] ?? '');
            if ($current !== '' && isset($fixes[sha1(trim($current))])) {
                $map[$ar][$locale] = $fixes[sha1(trim($current))];
                $changed = true;
            }
        }
        if ($changed) {
            $settings->set('market.translations', $map);
        }
    }

    /**
     * Entity pages list what mentions an entity, ordered by the mention's
     * created_at, which PublishArticle aligns to the story's published_at. A
     * translation published here never went through PublishArticle, so its
     * mentions kept the import time and every edition ordered a company page
     * differently. Opportunities were imported without their mentions at all,
     * so no entity page outside Arabic listed them. Both repaired here;
     * idempotent, and nothing an editor added is touched.
     */
    private function mentions(string $locale): void
    {
        DB::table('entity_mentions')
            ->join('articles', fn ($j) => $j->on('articles.id', '=', 'entity_mentions.mentionable_id')->where('entity_mentions.mentionable_type', 'article'))
            ->where('articles.locale', $locale)->whereNotNull('articles.source_article_id')->whereNotNull('articles.published_at')
            ->whereColumn('entity_mentions.created_at', '!=', 'articles.published_at')
            ->update(['entity_mentions.created_at' => DB::raw('articles.published_at')]);

        foreach (DB::table('opportunities')->where('locale', $locale)->whereNotNull('translation_group_id')->get(['id', 'translation_group_id']) as $copy) {
            $original = DB::table('opportunities')->where('translation_group_id', $copy->translation_group_id)->where('locale', Locales::default())->value('id');
            if ($original === null) {
                continue;
            }
            foreach (DB::table('entity_mentions')->where('mentionable_type', 'opportunity')->where('mentionable_id', $original)->get() as $mention) {
                $exists = DB::table('entity_mentions')->where('mentionable_type', 'opportunity')->where('mentionable_id', $copy->id)
                    ->where('entity_type', $mention->entity_type)->where('entity_id', $mention->entity_id)->exists();
                if (! $exists) {
                    $row = (array) $mention;
                    unset($row['id']);
                    DB::table('entity_mentions')->insert([...$row, 'mentionable_id' => $copy->id]);
                }
            }
        }
    }
}
