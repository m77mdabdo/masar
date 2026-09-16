<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DemoMode;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Run after every deploy and every restore. Observes the artefact, not its
 * inputs.
 *
 * The decay check reported "navigation renders — 23 menu items" while the site
 * served a masthead with no navigation, because it counted rows. This asks the
 * rendered page instead, and it asks the four questions whose answers have
 * actually been wrong on this project:
 *
 *   - does the page have navigation and a ticker, or is a cache hiding them
 *   - is robots.txt coming from the route, or from a static file shadowing it
 *   - is a worker running, or is the async half of the system merely queued
 *   - is the indexing switch in the state we believe it is in
 *
 * Exits non-zero on any failure, so a deploy script can refuse to announce
 * itself finished.
 */
class SmokeCheck extends Command
{
    protected $signature = 'masar:smoke
        {--expect-demo= : Assert demo mode is on|off, and fail if it is not}';

    protected $description = 'Post-deploy smoke check: is the served site actually right';

    /** @var array<int, array{0: string, 1: bool, 2: string}> */
    private array $results = [];

    public function handle(DemoMode $demo): int
    {
        $locale = (string) config('masar.default_locale', 'ar');

        $this->components->info('MASAR smoke check — '.config('app.url'));

        $home = $this->render("/{$locale}");

        $this->check(
            'homepage responds',
            $home !== null,
            $home !== null ? strlen($home).' bytes' : 'did not render',
        );

        $this->checkNavigation($home);
        $this->checkTicker($home);
        $this->checkRobots();
        $this->checkWorker();
        $this->checkDemoMode($demo);
        $this->checkAssets();
        $this->checkMediaDisk();

        $this->newLine();
        $failed = array_values(array_filter($this->results, fn (array $r): bool => ! $r[1]));

        $this->table(
            ['check', '', 'detail'],
            array_map(fn (array $r): array => [$r[0], $r[1] ? 'ok' : 'FAIL', $r[2]], $this->results),
        );

        if ($failed !== []) {
            $this->newLine();
            $this->error(count($failed).' smoke check(s) failed. The deploy is not finished.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Smoke check passed.');

        return self::SUCCESS;
    }

    private function check(string $name, bool $ok, string $detail): void
    {
        $this->results[] = [$name, $ok, $detail];
        $this->components->twoColumnDetail($name, ($ok ? '<fg=green>ok</>' : '<fg=red>FAIL</>').' — '.$detail);
    }

    private function checkNavigation(?string $home): void
    {
        if ($home === null) {
            $this->check('masthead has navigation', false, 'no homepage to read');

            return;
        }

        $header = preg_match('/<header\b.*?<\/header>/s', $home, $m) === 1 ? $m[0] : '';
        $links = preg_match_all('/<a\b[^>]*>/i', $header);
        $inDb = (int) DB::table('menu_items')->where('is_active', true)->count();

        // The masthead carries a wordmark and a newsletter link with no menu at
        // all, so "some links" is not the question.
        $this->check(
            'masthead has navigation',
            $links >= 4,
            "{$links} links in <header>, {$inDb} active menu items in db",
        );
    }

    private function checkTicker(?string $home): void
    {
        $dated = DB::table('settings')->where('key', 'market.as_of')->exists();

        if (! $dated) {
            // The gate working as designed is not a failure.
            $this->check('market ticker', true, 'no market.as_of — correctly withheld');

            return;
        }

        $this->check(
            'market ticker',
            $home !== null && str_contains($home, 'aria-label="مؤشرات السوق"'),
            $home !== null && str_contains($home, 'aria-label="مؤشرات السوق"')
                ? 'rendered'
                : 'market.as_of is set but the band is not on the page',
        );
    }

    private function checkRobots(): void
    {
        // The specific failure: a static public/robots.txt wins over the route
        // under any server that tries files first, and the whole editable
        // robots feature plus the demo-mode block silently stop existing.
        if (file_exists(public_path('robots.txt'))) {
            $this->check('robots.txt comes from the route', false, 'public/robots.txt exists and shadows the route');

            return;
        }

        $body = $this->render('/robots.txt');

        $this->check(
            'robots.txt comes from the route',
            $body !== null && str_contains($body, 'User-agent:'),
            $body === null ? 'route did not respond' : 'served by the route, no static file',
        );
    }

    private function checkWorker(): void
    {
        try {
            $depth = (int) Redis::connection()->llen('queues:default');
        } catch (Throwable $e) {
            $this->check('queue worker is running', false, 'redis unreachable: '.$e->getMessage());

            return;
        }

        $ps = Process::run("ps ax -o command | grep -E 'artisan (horizon|queue:work)' | grep -v grep");
        $running = trim($ps->output()) !== '';
        $failed = (int) DB::table('failed_jobs')->count();

        $this->check(
            'queue worker is running',
            $running,
            sprintf('worker %s · depth %d · failed %d', $running ? 'up' : 'NOT RUNNING', $depth, $failed),
        );
    }

    private function checkDemoMode(DemoMode $demo): void
    {
        $on = $demo->isOn();
        $expected = $this->option('expect-demo');

        if ($expected === null) {
            // No expectation given: report the state rather than judging it.
            $this->check('demo mode', true, $on ? 'ON — site is not indexed' : 'off — site is indexable');

            return;
        }

        $want = $expected === 'on';

        $this->check(
            'demo mode',
            $on === $want,
            $on === $want
                ? ($on ? 'ON as expected' : 'off as expected')
                : 'expected '.$expected.' but it is '.($on ? 'on' : 'off'),
        );
    }

    private function checkAssets(): void
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            $this->check('built assets present', false, 'public/build/manifest.json missing — the site serves no CSS');

            return;
        }

        $entries = (array) json_decode((string) file_get_contents($manifest), true);
        $missing = [];

        foreach ($entries as $entry) {
            $file = $entry['file'] ?? null;

            if (is_string($file) && ! is_file(public_path('build/'.$file))) {
                $missing[] = $file;
            }
        }

        $this->check(
            'built assets present',
            $missing === [],
            $missing === []
                ? count($entries).' entries, every file on disk'
                : 'manifest points at missing files: '.implode(', ', array_slice($missing, 0, 3)),
        );
    }

    /**
     * The media disk is round-tripped, not inspected.
     *
     * A misconfigured object store does not announce itself — it accepts a
     * write and the file is nowhere, or it rejects one and the editor sees a
     * broken image an hour later. Writing a byte and reading it back is the
     * only answer that means anything, and it is cheap.
     */
    private function checkMediaDisk(): void
    {
        $name = (string) config('media-library.disk_name');
        $required = ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET', 'R2_ENDPOINT'];

        if ($name === 'r2') {
            $missing = array_values(array_filter($required, fn (string $k): bool => blank(env($k))));

            if ($missing !== []) {
                $this->check('media disk', false, 'MEDIA_DISK=r2 but missing: '.implode(', ', $missing));

                return;
            }
        }

        $probe = 'health/smoke-'.bin2hex(random_bytes(6)).'.txt';

        try {
            $disk = Storage::disk($name);
            $disk->put($probe, 'ok');
            $read = $disk->get($probe);
            $disk->delete($probe);

            $this->check(
                'media disk',
                $read === 'ok',
                $read === 'ok' ? "{$name}: wrote and read back" : "{$name}: wrote but read back wrong",
            );
        } catch (Throwable $e) {
            $this->check('media disk', false, $name.': '.$e->getMessage());
        }
    }

    private function render(string $path): ?string
    {
        try {
            $response = app('router')->dispatch(Request::create($path, 'GET'));

            return $response->getStatusCode() === 200 ? (string) $response->getContent() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
