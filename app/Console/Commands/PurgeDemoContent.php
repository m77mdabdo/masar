<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Company;
use App\Models\IntelligenceItem;
use App\Models\Media;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Source;
use App\Models\SourceItem;
use App\Models\Subscriber;
use App\Models\Topic;
use App\Models\User;
use App\Support\DemoContent;
use App\Support\ViewCounter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Removes every row demo seeding created, and everything that points at one.
 *
 * Demo content lives in the database that will become production. This is the
 * exit, and it was built before the content: a purge that leaves an entity
 * mention pointing at a deleted article, an audit entry about a company that no
 * longer exists, or a homepage section pinned to a missing id has not removed
 * the demo — it has hidden it.
 *
 * Ids are captured before anything is deleted, and after the purge every place
 * that can reference a row by id is searched for those ids. Any hit is
 * reported and the command fails.
 *
 * Deletes go through the query builder, not Eloquent, so removing demo rows
 * fires no model events and writes no new audit entries. Media is the
 * exception: it goes through the model so its files leave the disk with it.
 */
class PurgeDemoContent extends Command
{
    protected $signature = 'masar:purge-demo
        {--dry-run : Report what would be removed without removing it}
        {--force : Do not ask for confirmation}';

    protected $description = 'Remove all demo content (is_demo) and every reference to it';

    /**
     * Flagged tables in deletion order: children before the parents a
     * RESTRICT foreign key protects (articles before users).
     *
     * @var array<int, class-string>
     */
    private const FLAGGED = [
        IntelligenceItem::class,
        SourceItem::class,
        Source::class,
        Article::class,
        Opportunity::class,
        Person::class,
        Company::class,
        Topic::class,
        Subscriber::class,
        Setting::class,
        User::class,
    ];

    public function handle(ViewCounter $views): int
    {
        $ids = $this->captureIds();
        $plan = $this->plan($ids);

        $this->table(['what', 'rows'], collect($plan)->map(fn (int $n, string $what): array => [$what, $n])->values()->all());

        if (array_sum($plan) === 0) {
            $this->info('No demo content to remove.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was removed.');

            return self::SUCCESS;
        }

        if ($blocker = $this->restrictedReference($ids)) {
            $this->error($blocker);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Remove all demo content listed above?')) {
            return self::FAILURE;
        }

        DB::transaction(fn () => $this->purgeRows($ids));

        // Outside the transaction: each deletion removes files, which cannot
        // be rolled back, and a half-finished run is safe to repeat.
        Media::query()->where('is_demo', true)->get()->each->delete();

        $views->forget($ids['article']);
        Artisan::call('masar:recount');
        Cache::flush();

        $leftovers = $this->leftovers($ids);

        if ($leftovers !== []) {
            $this->error('Demo content is gone but references to it remain:');
            $this->table(['where', 'rows'], collect($leftovers)->map(fn (int $n, string $w): array => [$w, $n])->values()->all());

            return self::FAILURE;
        }

        $this->info('Demo content removed. No reference to it remains.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, int>> morph alias (or table) => ids
     */
    private function captureIds(): array
    {
        $ids = [];

        foreach ([...self::FLAGGED, Media::class] as $class) {
            $model = new $class;
            $ids[$this->key($model)] = DB::table($model->getTable())->where('is_demo', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return $ids;
    }

    /**
     * @param  array<string, array<int, int>>  $ids
     * @return array<string, int>
     */
    private function plan(array $ids): array
    {
        $plan = [];

        foreach ([...self::FLAGGED, Media::class] as $class) {
            $model = new $class;
            $plan[$model->getTable()] = count($ids[$this->key($model)]);
        }

        foreach (['article_blocks', 'article_sources'] as $table) {
            $plan[$table] = DB::table($table)->where('is_demo', true)->orWhereIn('article_id', $ids['article'])->count();
        }

        $plan['entity_mentions'] = $this->mentions($ids)->count();
        $plan['activity_log'] = $this->activity($ids)->count();
        $plan['model_has_roles'] = DB::table('model_has_roles')->where('model_type', 'user')->whereIn('model_id', $ids['user'])->count();
        $plan['menu_items (linked to demo)'] = $this->menuLinks($ids)->count();
        $plan['homepage pins (ids unpinned)'] = $this->homepagePins($ids['article'])['count'];

        return $plan;
    }

    /**
     * A demo user who wrote or revised real content cannot be removed without
     * removing that content — the foreign keys are RESTRICT for that reason.
     * Refuse, and say which, rather than fail halfway.
     *
     * @param  array<string, array<int, int>>  $ids
     */
    private function restrictedReference(array $ids): ?string
    {
        $articles = DB::table('articles')->where('is_demo', false)->whereIn('author_id', $ids['user'])->pluck('id');
        $revisions = DB::table('article_revisions')
            ->whereIn('user_id', $ids['user'])
            ->whereNotIn('article_id', $ids['article'])
            ->count();

        if ($articles->isEmpty() && $revisions === 0) {
            return null;
        }

        return sprintf(
            'Refusing: demo users authored %d real article(s) [%s] and %d real revision(s). Reassign them first.',
            $articles->count(),
            $articles->implode(', '),
            $revisions,
        );
    }

    /**
     * @param  array<string, array<int, int>>  $ids
     */
    private function purgeRows(array $ids): void
    {
        $this->mentions($ids)->delete();
        $this->activity($ids)->delete();
        $this->menuLinks($ids)->delete();

        DB::table('model_has_roles')->where('model_type', 'user')->whereIn('model_id', $ids['user'])->delete();
        DB::table('model_has_permissions')->where('model_type', 'user')->whereIn('model_id', $ids['user'])->delete();
        DB::table('notifications')->where('notifiable_type', 'user')->whereIn('notifiable_id', $ids['user'])->delete();

        foreach ($this->homepagePins($ids['article'])['sections'] as $id => $config) {
            DB::table('homepage_sections')->where('id', $id)->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
        }

        // Children of demo articles cascade from articles; these are also
        // flagged in their own right, so remove any that hang off a real one.
        DB::table('article_blocks')->where('is_demo', true)->delete();
        DB::table('article_sources')->where('is_demo', true)->delete();

        foreach (self::FLAGGED as $class) {
            $model = new $class;
            DB::table($model->getTable())->whereIn('id', $ids[$this->key($model)])->delete();
        }
    }

    /**
     * @param  array<string, array<int, int>>  $ids
     */
    private function mentions(array $ids): Builder
    {
        return DB::table('entity_mentions')->where(function ($q) use ($ids): void {
            $q->where('is_demo', true);

            foreach (DemoContent::graphModels() as $class) {
                $alias = (new $class)->getMorphClass();
                $q->orWhere(fn ($m) => $m->where('mentionable_type', $alias)->whereIn('mentionable_id', $ids[$alias]))
                    ->orWhere(fn ($m) => $m->where('entity_type', $alias)->whereIn('entity_id', $ids[$alias]));
            }
        });
    }

    /**
     * Demo-time audit entries, and any entry about a demo row or by a demo
     * user, written before or after.
     *
     * @param  array<string, array<int, int>>  $ids
     */
    private function activity(array $ids): Builder
    {
        return DB::table('activity_log')->where(function ($q) use ($ids): void {
            $q->where('is_demo', true)
                ->orWhere(fn ($c) => $c->where('causer_type', 'user')->whereIn('causer_id', $ids['user']));

            // Only mapped models can be an audit subject: LogsActivity
            // requires the morph map.
            foreach (self::FLAGGED as $class) {
                $alias = array_search($class, Relation::morphMap(), true);

                if ($alias !== false) {
                    $q->orWhere(fn ($s) => $s->where('subject_type', $alias)->whereIn('subject_id', $ids[$alias]));
                }
            }
        });
    }

    /**
     * @param  array<string, array<int, int>>  $ids
     */
    private function menuLinks(array $ids): Builder
    {
        return DB::table('menu_items')->where(function ($q) use ($ids): void {
            $q->whereRaw('1 = 0');

            foreach (DemoContent::graphModels() as $class) {
                $alias = (new $class)->getMorphClass();
                $q->orWhere(fn ($m) => $m->where('linkable_type', $alias)->whereIn('linkable_id', $ids[$alias]));
            }
        });
    }

    /**
     * Homepage sections pin articles by id inside their JSON config. The
     * sections are site structure and stay; the demo ids come out of them.
     *
     * @param  array<int, int>  $articleIds
     * @return array{count: int, sections: array<int, array<string, mixed>>}
     */
    private function homepagePins(array $articleIds): array
    {
        $demo = array_flip($articleIds);
        $count = 0;
        $sections = [];

        foreach (DB::table('homepage_sections')->get(['id', 'config']) as $section) {
            $config = json_decode((string) $section->config, true) ?: [];
            $pinned = $config['article_ids'] ?? null;

            if (! is_array($pinned)) {
                continue;
            }

            $kept = array_values(array_filter($pinned, fn ($id): bool => ! isset($demo[(int) $id])));

            if (count($kept) !== count($pinned)) {
                $count += count($pinned) - count($kept);
                $config['article_ids'] = $kept;
                $sections[(int) $section->id] = $config;
            }
        }

        return ['count' => $count, 'sections' => $sections];
    }

    /**
     * Every place a purged id could still be referenced.
     *
     * @param  array<string, array<int, int>>  $ids
     * @return array<string, int>
     */
    private function leftovers(array $ids): array
    {
        $found = [];

        foreach ([...self::FLAGGED, Media::class] as $class) {
            $model = new $class;
            $found[$model->getTable().' still flagged'] = DB::table($model->getTable())->where('is_demo', true)->count();
        }

        foreach (['article_blocks', 'article_sources', 'entity_mentions', 'activity_log'] as $table) {
            $found[$table.' still flagged'] = DB::table($table)->where('is_demo', true)->count();
        }

        $found['entity_mentions → purged row'] = $this->mentions($ids)->count();
        $found['activity_log → purged row'] = $this->activity($ids)->count();
        $found['menu_items → purged row'] = $this->menuLinks($ids)->count();
        $found['homepage pins → purged article'] = $this->homepagePins($ids['article'])['count'];
        $found['media → purged owner'] = collect(DemoContent::graphModels())
            ->sum(fn (string $class): int => DB::table('media')->where('model_type', (new $class)->getMorphClass())->whereIn('model_id', $ids[(new $class)->getMorphClass()])->count());
        $found['article children → purged article'] = DB::table('article_blocks')->whereIn('article_id', $ids['article'])->count()
            + DB::table('article_sources')->whereIn('article_id', $ids['article'])->count()
            + DB::table('article_topic')->whereIn('article_id', $ids['article'])->count();
        $found['model_has_roles → purged user'] = DB::table('model_has_roles')->where('model_type', 'user')->whereIn('model_id', $ids['user'])->count();
        $found['people.company_id → purged company'] = DB::table('people')->whereIn('company_id', $ids['company'])->count();
        $found['intelligence_items.article_id → purged article'] = DB::table('intelligence_items')->whereIn('article_id', $ids['article'])->count();

        return array_filter($found);
    }

    /**
     * The key ids are stored under: the morph alias where one exists, else the
     * table. Read from the map, not getMorphClass(), which throws for an
     * unmapped model under enforceMorphMap().
     */
    private function key(object $model): string
    {
        $alias = array_search($model::class, Relation::morphMap(), true);

        return $alias === false ? $model->getTable() : (string) $alias;
    }
}
