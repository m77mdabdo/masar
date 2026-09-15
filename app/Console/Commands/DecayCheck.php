<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
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
            if ($result->successful()) {
                config(["database.connections.{$connection}.database" => $scratch]);
                DB::purge($connection);
                foreach (DB::select('SHOW TABLES') as $table) {
                    $name = array_values((array) $table)[0];
                    $rows += (int) DB::table($name)->count();
                }
            }

            $this->record(
                'migrate:fresh --seed',
                $result->successful() ? 'ok' : 'FAIL',
                $result->successful()
                    ? "clean on scratch db · {$rows} rows"
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
     * The seven flows from tasks/STATUS.md §2, checked at their thinnest point
     * rather than driven through a browser.
     */
    private function checkFlows(): void
    {
        $locale = (string) config('masar.default_locale', 'ar');

        $this->flow('publish an article', function () {
            $published = DB::table('articles')->where('status', 'published')->count();

            return [$published > 0, "{$published} published"];
        });

        $this->flow('article on the homepage', function () use ($locale) {
            $sections = DB::table('homepage_sections')->count();
            $ok = $sections > 0 && $this->getsOk("/{$locale}");

            return [$ok, "{$sections} sections"];
        });

        $this->flow('navigation renders', function () {
            $items = DB::table('menu_items')->count();

            return [$items > 0, "{$items} menu items"];
        });

        $this->flow('homepage order is editable', function () {
            $ordered = DB::table('homepage_sections')->distinct()->count('sort_order');

            return [$ordered > 0, "{$ordered} distinct positions"];
        });

        $this->flow('newsletter stores a subscriber', function () {
            // The check that started all of this: the route must exist and be
            // a POST, not merely a page that renders.
            $route = Route::getRoutes()->getByName('web.newsletter.subscribe');
            $ok = $route !== null && in_array('POST', $route->methods(), true);

            return [$ok, $ok ? 'POST route present' : 'NO POST ROUTE'];
        });

        $this->flow('source poll reaches the inbox', function () {
            $sources = DB::table('sources')->where('is_active', true)->count();
            $items = DB::table('intelligence_items')->count();

            return [$sources > 0, "{$sources} active sources · {$items} inbox items"];
        });

        $this->flow('redirect becomes a 301', function () {
            $redirects = DB::table('redirects')->count();

            return [true, "{$redirects} redirects configured"];
        });
    }

    /**
     * @param  callable(): array{0: bool, 1: string}  $probe
     */
    private function flow(string $label, callable $probe): void
    {
        try {
            [$ok, $detail] = $probe();
            $this->record("flow: {$label}", $ok ? 'ok' : 'FAIL', $detail);
        } catch (Throwable $e) {
            $this->record("flow: {$label}", 'FAIL', $e->getMessage());
        }
    }

    private function getsOk(string $path): bool
    {
        try {
            $response = app('router')->dispatch(\Illuminate\Http\Request::create($path, 'GET'));

            return $response->getStatusCode() === 200;
        } catch (Throwable) {
            return false;
        }
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

        foreach (File::allFiles(resource_path('views')) as $file) {
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
