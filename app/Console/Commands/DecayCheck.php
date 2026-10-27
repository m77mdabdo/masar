<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\EntityUrl;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The five-minute pulse check at the end of a task (CLAUDE.md §7).
 *
 * It exists because detail work hides decay. A subscribe form sat on every page
 * of the public site as `method="GET"` with no action for six sessions while
 * attention went to a hero scrim — visible the whole time, and nobody looked.
 * None of these checks is clever. They are the questions nobody thinks to ask
 * twice.
 */
class DecayCheck extends Command
{
    protected $signature = 'masar:decay
        {--with-seed : Also run migrate:fresh --seed against a scratch database (~15 min)}';

    protected $description = 'Five-minute decay check: suite, queue, seed, end-to-end flows, public forms';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $this->components->info('MASAR decay check');

        $this->checkSuite();
        $this->checkQueue();
        $this->checkSeed();
        $this->checkFlows();
        $this->checkPublicForms();

        $this->newLine();
        $this->table(['check', 'state', 'detail'], $this->rows);

        $failed = array_filter($this->rows, fn (array $r): bool => $r[1] === 'FAIL');

        if ($failed !== []) {
            $this->newLine();
            $this->error(count($failed).' check(s) failed. Say so in REPORT.md rather than moving on.');

            // The most common cause of a rendered-output failure, and the one
            // no application code can prevent: a raw SQL restore does not run
            // the seeder, so nothing flushes the cache the way seeding does.
            $rendering = array_filter(
                $failed,
                fn (array $row): bool => str_starts_with($row[0], 'flow: navigation') || str_starts_with($row[0], 'flow: market'),
            );

            if ($rendering !== []) {
                $this->newLine();
                $this->warn('If the database was just restored from a dump, try `php artisan cache:clear` first:');
                $this->warn('an import does not run DatabaseSeeder, so nothing flushed the caches it left behind.');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('No decay found. Paste the table above into REPORT.md.');

        return self::SUCCESS;
    }

    private function record(string $check, string $state, string $detail): void
    {
        $this->rows[] = [$check, $state, $detail];
        $this->components->twoColumnDetail($check, $state.' — '.$detail);
    }

    /**
     * The suite is NOT run from here.
     *
     * `php artisan test` hangs when its output is read through a pipe — the
     * tests finish (measured: 679 passed in 50s) and then the process sits at
     * 0% CPU because something spawned during the run inherits stdout and
     * outlives Pest, so the reader never sees EOF. Laravel's Process::run()
     * captures output exactly that way, so any shelling out from here hangs
     * too. See the pipe row in CLAUDE.md §9.
     *
     * The suite is one short command. The report carries its result beside
     * this table.
     */
    private function checkSuite(): void
    {
        $this->record('suite', 'MANUAL', 'run `php artisan test` and record it in REPORT.md');
    }

    private function checkQueue(): void
    {
        try {
            $depth = (int) Redis::connection()->llen('queues:default');
        } catch (Throwable $e) {
            $this->record('queue', 'FAIL', 'redis unreachable: '.$e->getMessage());

            return;
        }

        $failed = (int) DB::table('failed_jobs')->count();

        // A worker that is not running is the condition that let 1,771 jobs
        // pile up unnoticed, so it is checked directly rather than inferred
        // from the depth.
        $ps = Process::run("ps ax -o command | grep -E 'artisan (horizon|queue:work)' | grep -v grep");
        $workerRunning = trim($ps->output()) !== '';

        $state = (! $workerRunning && $depth > 0) || $failed > 0 ? 'FAIL' : ($workerRunning ? 'ok' : 'WARN');

        $this->record('queue', $state, sprintf(
            'depth %d · failed %d · worker %s',
            $depth, $failed, $workerRunning ? 'running' : 'NOT RUNNING',
        ));
    }

    private function checkSeed(): void
    {
        /*
         * Opt-in, because it is not a five-minute check. The seed transcodes
         * video and normalises every demo photograph, and measured at roughly
         * thirteen to fifteen minutes here — longer than the whole pulse it is
         * supposed to be part of. Run it with --with-seed before a release, or
         * whenever a migration changed.
         */
        if (! $this->option('with-seed')) {
            $this->record('migrate:fresh --seed', 'SKIP', 'not run — use --with-seed (~15 min)');

            return;
        }

        // A scratch database, never the working one. The point of this check is
        // that a fresh install still works, not to destroy the editor's data.
        $scratch = 'masar_decay_'.now()->format('Hisv');
        $connection = config('database.default');

        // Captured before anything overwrites it. Reading it back inside the
        // `finally` would read the scratch name and restore nothing, which left
        // every later check querying a database that had just been dropped.
        $original = config("database.connections.{$connection}.database");

        try {
            DB::statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $result = Process::timeout(2400)->env([
                'DB_DATABASE' => $scratch,
            ])->run('php artisan migrate:fresh --seed --force');

            $rows = 0;
            $published = 0;
            if ($result->successful()) {
                config(["database.connections.{$connection}.database" => $scratch]);
                DB::purge($connection);
                foreach (DB::select('SHOW TABLES') as $table) {
                    $name = array_values((array) $table)[0];
                    $rows += (int) DB::table($name)->count();
                }

                // An exit code of 0 is the process's claim, not the seed's
                // result. A run that migrated and seeded nothing used to read
                // "ok · 0 rows".
                if (DB::getSchemaBuilder()->hasTable('articles')) {
                    $published = (int) DB::table('articles')->where('status', 'published')->count();
                }
            }

            $seeded = $result->successful() && $rows > 0 && $published > 0;

            $this->record(
                'migrate:fresh --seed',
                $seeded ? 'ok' : 'FAIL',
                $result->successful()
                    ? "scratch db · {$rows} rows · {$published} published articles"
                    : trim(substr($result->errorOutput() ?: $result->output(), -160)),
            );
        } catch (Throwable $e) {
            $this->record('migrate:fresh --seed', 'FAIL', $e->getMessage());
        } finally {
            config(["database.connections.{$connection}.database" => $original]);
            DB::purge($connection);
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$scratch}`");
            } catch (Throwable) {
                $this->warn("Could not drop scratch database {$scratch}; drop it by hand.");
            }
        }
    }

    /**
     * The flows from tasks/STATUS.md §2, each checked against what a reader
     * would actually get.
     *
     * Every row here must be able to go FAIL, and each has a test in
     * tests/Feature/DecayCheckTest.php that breaks the thing and watches it
     * fail. Three rows used to report "ok" without observing anything — a row
     * count for publishing, a route's existence for the newsletter, and a
     * redirect check that passed because there were no redirects. A row with
     * nothing to observe says SKIP; it never says ok.
     */
    private function checkFlows(): void
    {
        $locale = (string) config('masar.default_locale', 'ar');

        $this->flow('published article is served', function () use ($locale) {
            $article = Article::query()->published()->where('locale', $locale)
                ->latest('published_at')->first();

            if ($article === null) {
                return [false, 'no published article to serve'];
            }

            $url = app(EntityUrl::class)->for($article, $locale);
            $html = $url === null ? null : $this->render($url);

            if ($html === null) {
                return [false, "#{$article->id} did not render at {$url}"];
            }

            $ok = str_contains($html, e($article->title));

            return [$ok, $ok ? "#{$article->id} renders with its headline" : "#{$article->id} rendered without its headline"];
        });

        $this->flow('homepage links published articles', function () use ($locale) {
            $html = $this->render("/{$locale}");

            if ($html === null) {
                return [false, 'homepage did not render'];
            }

            $urls = Article::query()->published()->where('locale', $locale)
                ->with('category:id,slug')->latest('published_at')->limit(50)->get()
                ->map(fn (Article $a): ?string => app(EntityUrl::class)->for($a, $locale))
                ->filter();

            $linked = $urls->filter(fn (string $url): bool => str_contains($html, 'href="'.e(url($url)).'"')
                || str_contains($html, 'href="'.e($url).'"'))->count();

            return [$linked > 0, "{$linked} of the latest {$urls->count()} published articles linked from /{$locale}"];
        });

        /*
         * Reads the rendered page, not the table.
         *
         * The previous version counted rows in `menu_items` and passed while
         * the site served a masthead with no navigation at all — the items were
         * in the database and an empty cached snapshot was in front of them.
         * A check that asks the database whether the page is right is not a
         * check on the page.
         */
        $this->flow('navigation renders in the masthead', function () use ($locale) {
            $expected = DB::table('menu_items')->count();
            $html = $this->render("/{$locale}");

            if ($html === null) {
                return [false, 'homepage did not render'];
            }

            $header = preg_match('/<header\b.*?<\/header>/s', $html, $m) === 1 ? $m[0] : '';
            $links = preg_match_all('/<a\b[^>]*>/i', $header);

            // The masthead carries the wordmark and the newsletter link even
            // with no menu, so "some links" is not the test — it needs enough
            // to be a navigation.
            return [$links >= 4, "{$links} links in <header>, {$expected} menu items in db"];
        });

        // A data check, named as one. Editability is the composer's job and is
        // covered by the suite; what can decay at rest is two sections sharing
        // a position, which renders them in whatever order MySQL returns.
        $this->flow('homepage sections have distinct positions', function () {
            $layout = DB::table('homepage_layouts')->where('is_active', true)->value('id');
            $total = DB::table('homepage_sections')->where('layout_id', $layout)->count();
            $distinct = DB::table('homepage_sections')->where('layout_id', $layout)->distinct()->count('sort_order');

            if ($total === 0) {
                return [false, 'active layout has no sections'];
            }

            return [$total === $distinct, "{$distinct} distinct positions for {$total} sections"];
        });

        // The check that started all of this: a form on every page that looked
        // like it subscribed and posted nowhere. Read off the rendered page,
        // then resolved against the router as the browser would submit it.
        $this->flow('newsletter form posts to the subscribe route', function () use ($locale) {
            $html = $this->render("/{$locale}");

            if ($html === null) {
                return [false, 'homepage did not render'];
            }

            preg_match_all('/<form\b[^>]*>/i', $html, $forms);

            foreach ($forms[0] as $tag) {
                $method = preg_match('/\bmethod\s*=\s*["\']?(\w+)/i', $tag, $m) === 1 ? strtoupper($m[1]) : 'GET';
                $action = preg_match('/\baction\s*=\s*["\']([^"\']*)/i', $tag, $a) === 1 ? html_entity_decode($a[1]) : '';

                if ($method !== 'POST' || $action === '') {
                    continue;
                }

                try {
                    $route = Route::getRoutes()->match(Request::create($action, 'POST'));
                } catch (Throwable) {
                    continue;
                }

                if ($route->getName() === 'web.newsletter.subscribe') {
                    return [true, 'rendered form POSTs to web.newsletter.subscribe'];
                }
            }

            return [false, count($forms[0]).' form(s) on /'.$locale.', none POSTs to web.newsletter.subscribe'];
        });

        $this->flow('source polling succeeds', function () {
            $active = DB::table('sources')->where('is_active', true)->get(['last_success_at', 'poll_frequency_minutes']);

            // Polling is off until the crawler's contact details resolve
            // (CLAUDE.md §5). Off means nothing is polled, so there is nothing
            // to observe — which is not the same as healthy.
            if (! config('masar.intelligence.polling_enabled')) {
                return ['SKIP', "polling disabled — {$active->count()} active sources, none polled"];
            }

            $fresh = $active->filter(fn ($s): bool => $s->last_success_at !== null
                && now()->diffInMinutes($s->last_success_at, true) <= 2 * max(1, (int) $s->poll_frequency_minutes))->count();

            return [$active->isNotEmpty() && $fresh > 0, "{$fresh} of {$active->count()} active sources succeeded within twice their interval"];
        });

        $this->flow('market ticker renders', function () use ($locale) {
            // The as_of gate is meant to hide undated figures. It cannot tell
            // the difference between "no figures" and "figures the cache is
            // hiding", so the rendered page is the only honest check.
            $dated = DB::table('settings')->where('key', 'market.as_of')->exists();
            $html = $this->render("/{$locale}");
            $rendered = $html !== null && str_contains($html, 'aria-label="مؤشرات السوق"');

            if (! $dated) {
                return [true, 'no market.as_of — ticker correctly withheld'];
            }

            return [$rendered, $rendered ? 'rendered' : 'market.as_of is set but no ticker on the page'];
        });

        // Requests each configured redirect through the HTTP kernel, where
        // HandleMissingPages lives, inside a rolled-back transaction so the
        // probe does not count as traffic.
        $this->flow('configured redirects answer', function () {
            $redirects = DB::table('redirects')->orderBy('id')->limit(20)->get();

            if ($redirects->isEmpty()) {
                return ['SKIP', '0 redirects configured — nothing to observe'];
            }

            $broken = [];

            foreach ($redirects as $redirect) {
                DB::beginTransaction();

                try {
                    $response = $this->fetch($redirect->from_path);
                } finally {
                    DB::rollBack();
                }

                $location = $response?->headers->get('Location') ?? '';

                if ($response?->getStatusCode() !== (int) $redirect->status_code || ! str_ends_with($location, $redirect->to_path)) {
                    $broken[] = $redirect->from_path.' → '.($response?->getStatusCode() ?? 'error');
                }
            }

            return $broken === []
                ? [true, "{$redirects->count()} of {$redirects->count()} answered with their status"]
                : [false, implode(' · ', array_slice($broken, 0, 3))];
        });
    }

    /**
     * @param  callable(): array{0: bool|string, 1: string}  $probe  true/false, or a state such as 'SKIP'
     */
    private function flow(string $label, callable $probe): void
    {
        try {
            [$state, $detail] = $probe();
            $this->record("flow: {$label}", is_string($state) ? $state : ($state ? 'ok' : 'FAIL'), $detail);
        } catch (Throwable $e) {
            $this->record("flow: {$label}", 'FAIL', $e->getMessage());
        }
    }

    /**
     * A public page as a reader gets it: through the HTTP kernel, so global
     * middleware runs. The router alone skips HandleMissingPages, which is
     * where redirects are served.
     */
    private function fetch(string $path): ?Response
    {
        try {
            return app(HttpKernel::class)->handle(Request::create($path, 'GET'));
        } catch (Throwable) {
            return null;
        }
    }

    /** The rendered HTML of a public page, or null if it did not render. */
    private function render(string $path): ?string
    {
        $response = $this->fetch($path);

        return $response?->getStatusCode() === 200 ? (string) $response->getContent() : null;
    }

    /**
     * Every form in the views, checked against the routes that actually exist.
     *
     * A GET form is legitimate for a filter or a search. A GET form that has no
     * action at all is the shape the newsletter shipped in: it looks like it
     * submits somewhere and it posts nowhere.
     */
    private function checkPublicForms(): void
    {
        $problems = [];
        $forms = 0;

        // The configured view paths, not a hard-coded directory: the same paths
        // the renderer reads, and the seam the test uses to plant a bad form.
        $files = collect((array) config('view.paths'))->flatMap(fn (string $path): array => File::allFiles($path));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // The admin panel builds its own forms through Livewire.
            if (str_contains($file->getPathname(), '/filament/')) {
                continue;
            }

            $markup = (string) file_get_contents($file->getPathname());

            preg_match_all('/<form\b[^>]*>/i', $markup, $matches);

            foreach ($matches[0] as $tag) {
                $forms++;
                $hasAction = preg_match('/\baction\s*=/i', $tag) === 1;
                $isPost = preg_match('/method\s*=\s*["\']post["\']/i', $tag) === 1;
                $isLivewire = preg_match('/\bwire:submit\b/i', $tag) === 1;

                if ($isLivewire) {
                    continue;
                }

                if (! $hasAction) {
                    $problems[] = $file->getFilename().': form with no action';

                    continue;
                }

                if ($isPost && ! str_contains($markup, '@csrf')) {
                    $problems[] = $file->getFilename().': POST form with no @csrf';
                }
            }
        }

        $this->record(
            'public forms',
            $problems === [] ? 'ok' : 'FAIL',
            $problems === [] ? "{$forms} forms, all addressed" : implode(' · ', array_slice($problems, 0, 3)),
        );
    }
}
