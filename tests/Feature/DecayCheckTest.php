<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Redirect;
use App\Models\Source;
use App\Support\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * masar:decay is the instrument this project is judged by, so every row is
 * held to one standard: break the thing the row claims to observe, and the row
 * must go FAIL. A row that cannot be made to fail is not a check.
 *
 * Each row is first shown `ok` against a working site, so a FAIL below means
 * the break was seen — not that the fixture never worked.
 *
 * The precedent is masar:recount, whose drift detector shared the bug it
 * measured and reported zero drift on data where every row was wrong.
 */

/** @return array<string, array{state: string, detail: string}> rows keyed by check name */
function decayRows(string $options = ''): array
{
    Artisan::call(trim('masar:decay '.$options));

    $rows = [];

    foreach (explode("\n", Artisan::output()) as $line) {
        if (preg_match('/^\|\s*(.+?)\s*\|\s*(ok|FAIL|WARN|SKIP|MANUAL)\s*\|\s*(.*?)\s*\|$/u', $line, $m) === 1) {
            $rows[$m[1]] = ['state' => $m[2], 'detail' => $m[3]];
        }
    }

    return $rows;
}

function decayState(string $check, string $options = ''): string
{
    $rows = decayRows($options);

    expect($rows)->toHaveKey($check);

    return $rows[$check]['state'];
}

/** A small but complete public site: articles, a homepage, a masthead menu, a dated ticker. */
function decaySite(): void
{
    $category = Category::factory()->create(['slug' => 'saudi', 'is_active' => true]);

    Article::factory()->count(6)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subHour(),
        'locale' => 'ar',
        'category_id' => $category->id,
    ]);

    $layout = HomepageLayout::factory()->create(['is_active' => true]);

    foreach ([1, 2] as $i) {
        $layout->sections()->create([
            'type' => 'leads',
            'title' => ['ar' => "قسم {$i}"],
            'source' => 'auto',
            'config' => ['limit' => 3],
            'sort_order' => $i,
            'is_visible' => true,
        ]);
    }

    $menu = Menu::factory()->create(['key' => 'header']);

    foreach (['السعودية', 'الأعمال', 'رؤى', 'الأسواق'] as $i => $label) {
        MenuItem::factory()->create([
            'menu_id' => $menu->getKey(),
            'label' => ['ar' => $label, 'en' => 'Section '.$i],
            'url' => '/ar/saudi',
            'linkable_type' => null,
            'linkable_id' => null,
            'sort_order' => $i + 1,
            'is_active' => true,
            'show_desktop' => true,
            'show_mobile' => true,
        ]);
    }

    app(Settings::class)->setMany([
        'market.as_of' => now()->subHours(3)->format('Y-m-d H:i'),
        'market.source' => 'أرقام تجريبية — ليست قراءة سوق',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,235', 'change' => 0.4, 'series' => '11150,11180,11235'],
        ],
    ]);
}

/** Puts a replacement view in front of the real one for the rest of the test. */
function overrideView(string $name, string $markup): void
{
    $dir = storage_path('framework/testing/decay-views-'.uniqid());
    $file = $dir.'/'.str_replace('.', '/', $name).'.blade.php';

    File::ensureDirectoryExists(dirname($file));
    File::put($file, $markup);

    View::getFinder()->prependLocation($dir);
    View::getFinder()->flush();

    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
}

beforeEach(function (): void {
    // `ps` is the worker probe; faked so the result does not depend on whether
    // Horizon happens to be running on the machine that runs the suite.
    // Keyed on `ps` rather than `*`: fakes match in registration order, and a
    // catch-all registered here would also answer for migrate:fresh below.
    Process::fake(['ps *' => Process::result('php artisan horizon')]);
});

// ── suite ───────────────────────────────────────────────────────────────────

it('never reports the suite as ok, because it does not run it', function (): void {
    expect(decayState('suite'))->toBe('MANUAL');
});

// ── queue ───────────────────────────────────────────────────────────────────

it('passes the queue with a worker and nothing failed', function (): void {
    expect(decayState('queue'))->toBe('ok');
});

it('fails the queue when a job has failed', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException: test',
        'failed_at' => now(),
    ]);

    expect(decayState('queue'))->toBe('FAIL');
});

it('fails the queue when jobs wait and no worker is running', function (): void {
    Process::fake(['ps *' => Process::result('')]);
    Redis::connection()->rpush('queues:default', '{}');

    try {
        expect(decayState('queue'))->toBe('FAIL');
    } finally {
        Redis::connection()->del('queues:default');
    }
});

// ── migrate:fresh --seed ────────────────────────────────────────────────────

it('fails the seed check when migrate:fresh --seed fails', function (): void {
    Process::fake(['*migrate:fresh*' => Process::result('', 'SQLSTATE: seed exploded', 1)]);

    expect(decayState('migrate:fresh --seed', '--with-seed'))->toBe('FAIL');
    Process::assertRan(fn ($process): bool => str_contains($process->command, 'migrate:fresh --seed'));
});

it('fails the seed check when the seed exits 0 but leaves the database empty', function (): void {
    // The process claims success; the scratch database it was given is empty.
    Process::fake(['*migrate:fresh*' => Process::result('Seeding complete.')]);

    expect(decayState('migrate:fresh --seed', '--with-seed'))->toBe('FAIL');
});

// ── flow: published article is served ───────────────────────────────────────

it('passes when the newest published article renders with its headline', function (): void {
    decaySite();

    expect(decayState('flow: published article is served'))->toBe('ok');
});

it('fails when there is no published article to serve', function (): void {
    expect(decayState('flow: published article is served'))->toBe('FAIL');
});

it('fails when the article page renders without the headline', function (): void {
    decaySite();
    overrideView('pages.article', '<p>broken template</p>');

    expect(decayState('flow: published article is served'))->toBe('FAIL');
});

// ── flow: homepage links published articles ─────────────────────────────────

it('passes when the homepage links a published article', function (): void {
    decaySite();

    expect(decayState('flow: homepage links published articles'))->toBe('ok');
});

it('fails when the homepage renders but links no published article', function (): void {
    decaySite();
    DB::table('homepage_sections')->delete();

    expect(decayState('flow: homepage links published articles'))->toBe('FAIL');
});

// ── flow: navigation renders in the masthead ────────────────────────────────

it('passes when the masthead renders its menu', function (): void {
    decaySite();

    expect(decayState('flow: navigation renders in the masthead'))->toBe('ok');
});

it('fails when an empty cached menu sits in front of a populated table', function (): void {
    decaySite();

    // The incident this row exists for: rows intact, empty snapshot cached.
    Cache::tags(['navigation', 'navigation:header'])->put('menu:header:ar:desktop', collect(), 3600);

    expect(decayState('flow: navigation renders in the masthead'))->toBe('FAIL');
});

// ── flow: homepage sections have distinct positions ─────────────────────────

it('passes when every section has its own position', function (): void {
    decaySite();

    expect(decayState('flow: homepage sections have distinct positions'))->toBe('ok');
});

it('fails when two sections share a position', function (): void {
    decaySite();
    DB::table('homepage_sections')->update(['sort_order' => 1]);

    expect(decayState('flow: homepage sections have distinct positions'))->toBe('FAIL');
});

// ── flow: newsletter form posts to the subscribe route ──────────────────────

it('passes when the rendered newsletter form posts to the subscribe route', function (): void {
    decaySite();

    expect(decayState('flow: newsletter form posts to the subscribe route'))->toBe('ok');
});

it('fails when the rendered newsletter form is the GET-with-no-action shape it shipped in', function (): void {
    decaySite();
    overrideView('components.layout.newsletter-band', '<form method="GET"><input name="email"></form>');

    expect(decayState('flow: newsletter form posts to the subscribe route'))->toBe('FAIL');
});

// ── flow: source polling succeeds ───────────────────────────────────────────

it('skips source polling while polling is disabled rather than calling it healthy', function (): void {
    config(['masar.intelligence.polling_enabled' => false]);
    Source::factory()->create(['is_active' => true]);

    expect(decayState('flow: source polling succeeds'))->toBe('SKIP');
});

it('passes source polling when a source succeeded recently', function (): void {
    config(['masar.intelligence.polling_enabled' => true]);
    Source::factory()->create(['is_active' => true, 'poll_frequency_minutes' => 60, 'last_success_at' => now()->subMinutes(30)]);

    expect(decayState('flow: source polling succeeds'))->toBe('ok');
});

it('fails source polling when no source has succeeded within twice its interval', function (): void {
    config(['masar.intelligence.polling_enabled' => true]);
    Source::factory()->create(['is_active' => true, 'poll_frequency_minutes' => 60, 'last_success_at' => now()->subDays(3)]);

    expect(decayState('flow: source polling succeeds'))->toBe('FAIL');
});

// ── flow: market ticker renders ─────────────────────────────────────────────

it('passes when dated figures render as a ticker', function (): void {
    decaySite();

    expect(decayState('flow: market ticker renders'))->toBe('ok');
});

it('fails when an empty cached settings snapshot hides dated figures', function (): void {
    decaySite();
    Cache::tags('settings')->put('masar.settings.all', [], 3600);

    expect(decayState('flow: market ticker renders'))->toBe('FAIL');
});

// ── flow: configured redirects answer ───────────────────────────────────────

it('skips redirects when none are configured rather than calling them healthy', function (): void {
    expect(decayState('flow: configured redirects answer'))->toBe('SKIP');
});

it('passes when a configured redirect answers, without counting the probe as a hit', function (): void {
    $redirect = Redirect::factory()->create(['from_path' => '/ar/old-story', 'to_path' => '/ar/saudi', 'status_code' => 301]);

    expect(decayState('flow: configured redirects answer'))->toBe('ok')
        ->and($redirect->fresh()->hits)->toBe(0);
});

it('fails when a redirect is shadowed by a live page and never fires', function (): void {
    decaySite();
    Redirect::factory()->create(['from_path' => '/ar/saudi', 'to_path' => '/ar/business', 'status_code' => 301]);

    expect(decayState('flow: configured redirects answer'))->toBe('FAIL');
});

// ── public forms ────────────────────────────────────────────────────────────

it('passes the public forms of the real views', function (): void {
    expect(decayState('public forms'))->toBe('ok');
});

it('fails on a form with no action', function (): void {
    $dir = storage_path('framework/testing/decay-forms-'.uniqid());
    File::ensureDirectoryExists($dir);
    File::put($dir.'/subscribe.blade.php', '<form method="POST" class="x">@csrf</form>');

    config(['view.paths' => [$dir]]);

    try {
        expect(decayState('public forms'))->toBe('FAIL');
    } finally {
        File::deleteDirectory($dir);
    }
});

// ── the command as a whole ──────────────────────────────────────────────────

it('exits non-zero when any row fails', function (): void {
    // No published article: one FAIL is enough.
    expect(Artisan::call('masar:decay'))->toBe(1);
});
