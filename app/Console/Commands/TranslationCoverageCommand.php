<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Is every piece of public content present in every edition?
 *
 * Counts, per edition, what the Arabic original has and what that edition
 * has: published stories, entity names and descriptions, opportunities,
 * image alt text, bylines and market wording. Exits non-zero when anything
 * is missing, and names it, so a gap is reported rather than papered over
 * by the Arabic fallback.
 */
class TranslationCoverageCommand extends Command
{
    protected $signature = 'masar:translations:coverage';

    protected $description = 'Report public content missing from any edition';

    public function handle(): int
    {
        $default = Locales::default();
        $sources = Article::query()->published()->where('locale', $default)->whereNull('source_article_id')->get(['id', 'translation_group_id', 'title']);
        $rows = [];
        $gaps = [];

        foreach (Locales::codes() as $locale) {
            if ($locale === $default) {
                continue;
            }

            $translated = Article::query()->published()->where('locale', $locale)
                ->whereIn('translation_group_id', $sources->pluck('translation_group_id'))->pluck('translation_group_id')->all();
            foreach ($sources->whereNotIn('translation_group_id', $translated) as $missing) {
                $gaps[] = "{$locale}: story #{$missing->id} «{$missing->title}»";
            }

            $entities = 0;
            $entityTotal = 0;
            foreach (['company_translations' => ['company_id', 'description'], 'person_translations' => ['person_id', 'bio'], 'topic_translations' => ['topic_id', 'name'],
                'category_translations' => ['category_id', 'description'], 'industry_translations' => ['industry_id', 'name'], 'country_translations' => ['country_id', 'name'], 'market_translations' => ['market_id', 'name']] as $table => [$key, $col]) {
                foreach (DB::table($table)->where('locale', $default)->get() as $ar) {
                    $entityTotal++;
                    $row = DB::table($table)->where($key, $ar->{$key})->where('locale', $locale)->first();
                    $ok = $row !== null && (blank($ar->{$col}) || filled($row->{$col})) && filled($row->name ?? null);
                    $ok ? $entities++ : $gaps[] = "{$locale}: {$table} {$key}={$ar->{$key}}";
                }
            }

            $opps = DB::table('opportunities')->where('locale', $default)->count();
            $oppsHere = DB::table('opportunities')->where('locale', $locale)->count();
            if ($oppsHere < $opps) {
                $gaps[] = "{$locale}: ".($opps - $oppsHere).' opportunities';
            }

            $alts = DB::table('media')->get(['custom_properties'])->filter(function ($m) use ($locale): bool {
                $p = (array) json_decode((string) $m->custom_properties, true);

                return preg_match('/\p{Arabic}/u', (string) ($p['alt'] ?? '')) && ! isset($p['alt_translations'][$locale]);
            })->count();
            if ($alts) {
                $gaps[] = "{$locale}: {$alts} image alt texts";
            }

            $rows[] = [$locale, count($translated).'/'.$sources->count(), "{$entities}/{$entityTotal}", "{$oppsHere}/{$opps}", $alts === 0 ? 'complete' : "{$alts} missing"];
        }

        $this->table(['edition', 'published stories', 'entity records', 'opportunities', 'image alt text'], $rows);

        if ($gaps !== []) {
            $this->error(count($gaps).' gaps:');
            foreach (array_slice($gaps, 0, 50) as $gap) {
                $this->line('  '.$gap);
            }

            return self::FAILURE;
        }

        $this->info('Every published story and every entity is present in every edition.');

        return self::SUCCESS;
    }
}
