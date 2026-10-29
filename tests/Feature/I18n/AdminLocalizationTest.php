<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use App\Notifications\SourceDisabled;
use App\Support\Locales;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

/*
 * The admin panel in all six languages (owner's ruling, 2026-10-29, revoking
 * "admin stays Arabic"). Its text is keyed by the Arabic source in
 * lang/admin/{code}.json, so the code still reads in Arabic; Laravel's own
 * messages live in lang/{code}/*.php and Filament's missing keys in
 * lang/vendor. These tests hold the mechanism, not a snapshot of it: a new
 * Arabic literal that nobody wrapped, or a key one edition lacks, fails here.
 */

/** Files whose Arabic is data, not interface text, and why. */
const ADMIN_ARABIC_DATA = [
    'Actions/Support/TransliterateArabic.php' => 'transliteration table',
    'Support/ArabicNormaliser.php' => 'search folding table',
    'Queries/SearchArticlesQuery.php' => 'SQL folding of Arabic letters',
    'Services/Translation/SegmentGuard.php' => 'Urdu-letter guard',
    'Services/Intelligence/ImportanceScorer.php' => 'rationale stored on the item at ingest',
    'Listeners/RecordStatusAudit.php' => 'audit-log description, a stored record',
    'Console/Commands/' => 'operator console output and audit-log lines',
    'Http/Controllers/Web/RobotsController.php' => 'robots.txt comment',
];

/** Constants whose Arabic values are translated where they are used. */
const ADMIN_ARABIC_CONSTANTS = [
    'Filament/Resources/Roles/RoleResource.php',
    'Filament/Resources/Articles/Schemas/ArticleEntityFields.php',
    'Filament/Resources/Articles/Support/GateTabMap.php',
    'Actions/Intelligence/CreateDraftFromIntelligenceItem.php', // the draft's own Arabic title
    'Support/AuditDescription.php', // the stored descriptions it recognises; each is presented through __()
];

function adminCatalogue(string $code): array
{
    return json_decode((string) file_get_contents(lang_path("admin/{$code}.json")), true);
}

it('wraps every Arabic interface string in app/ in a translation call', function (): void {
    $unwrapped = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        $rel = substr($file->getPathname(), strlen(app_path()) + 1);
        if ($file->getExtension() !== 'php' || collect(array_keys(ADMIN_ARABIC_DATA))->contains(fn ($d) => str_starts_with($rel, $d))) {
            continue;
        }
        $tokens = array_values(array_filter(token_get_all((string) file_get_contents($file->getPathname())),
            fn ($t) => ! is_array($t) || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        foreach ($tokens as $i => $t) {
            if (! is_array($t) || ! in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) || ! preg_match('/\p{Arabic}/u', $t[1])) {
                continue;
            }
            $wrapped = ($tokens[$i - 1] ?? null) === '(' && is_array($tokens[$i - 2] ?? null) && $tokens[$i - 2][1] === '__';
            if (! $wrapped && ! in_array($rel, ADMIN_ARABIC_CONSTANTS, true)) {
                $unwrapped[] = "{$rel}:{$t[2]} ".mb_substr($t[1], 0, 40);
            }
        }
    }

    expect($unwrapped)->toBe([]);
});

it('has every admin string in every edition, with the same placeholders and in its own script', function (string $code): void {
    $source = adminCatalogue('ar');
    $target = adminCatalogue($code);
    $tokens = fn (string $s): array => tap(preg_match_all('/:[a-z_]+|%[0-9$]*[ds]/', $s, $m) ? $m[0] : [], fn (array &$a) => sort($a));

    expect(array_keys($target))->toBe(array_keys($source));
    foreach ($source as $key => $_) {
        expect(trim($target[$key]))->not->toBe('', "{$code}: empty «{$key}»")
            ->and($tokens($target[$key]))->toBe($tokens($key), "{$code}: placeholders differ in «{$key}»");
        if ($code !== 'ur-pk') {
            expect(preg_match('/\p{Arabic}/u', $target[$key]))->toBe(0, "{$code}: Arabic left in «{$key}»");
        } elseif (preg_match('/\p{L}/u', $key)) {
            // Punctuation-only keys (the «، » list separator) are the same in Urdu.
            expect($target[$key])->not->toBe($key, "ur-pk: Arabic copied across for «{$key}»");
        }
    }
})->with(['en', 'fr', 'hi', 'ur-pk', 'zh-hans']);

it('holds every key the panel can use: wrapped literals, views and translated constants', function (): void {
    $catalogue = adminCatalogue('ar');
    $used = [];
    foreach ([app_path(), resource_path('views/filament')] as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() === 'php' && preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*\\p{Arabic}(?:[^'\\\\]|\\\\.)*)'/u", (string) file_get_contents($file->getPathname()), $m)) {
                array_push($used, ...array_map(fn ($s) => str_replace(["\\'", '\\\\'], ["'", '\\'], $s), $m[1]));
            }
        }
    }
    foreach (\App\Filament\Resources\Articles\Schemas\ArticleEntityFields::KINDS as $kind) {
        array_push($used, ...array_filter([$kind['label'], $kind['hint']]));
    }
    array_push($used, ...array_values((new ReflectionClass(\App\Filament\Resources\Roles\RoleResource::class))->getConstant('AREAS')));
    array_push($used, ...array_column((new ReflectionClass(\App\Filament\Resources\Articles\Support\GateTabMap::class))->getConstant('MAP'), 'label'));

    expect(array_values(array_diff(array_unique($used), array_keys($catalogue))))->toBe([]);
});

it('resolves every Laravel and Filament message in every edition without falling back', function (string $code): void {
    $flat = function (array $a, string $p = '') use (&$flat): array {
        $o = [];
        foreach ($a as $k => $v) {
            $o += is_array($v) ? $flat($v, "{$p}{$k}.") : ["{$p}{$k}" => $v];
        }

        return $o;
    };
    $keys = [];
    foreach (['auth', 'pagination', 'passwords', 'validation'] as $group) {
        $keys = [...$keys, ...array_map(fn ($k) => "{$group}.{$k}", array_keys($flat(require lang_path("en/{$group}.php"))))];
    }
    foreach (glob(base_path('vendor/filament/*/resources/lang/en')) as $dir) {
        $package = basename(dirname($dir, 3));
        $namespace = ['filament' => 'filament-panels', 'support' => 'filament'][$package] ?? "filament-{$package}";
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $group = substr($file->getPathname(), strlen($dir) + 1, -4);
            $keys = [...$keys, ...array_map(fn ($k) => "{$namespace}::{$group}.{$k}", array_keys($flat(require $file->getPathname())))];
        }
    }

    $missing = array_values(array_filter($keys, fn (string $k): bool => app('translator')->get($k, [], $code, false) === $k));

    expect(count($keys))->toBeGreaterThan(1000)->and($missing)->toBe([]);
})->with(['ar', 'en', 'fr', 'hi', 'ur-pk', 'zh-hans']);

it('serves the sign-in page in the language the visitor picks, with its direction', function (string $code): void {
    $html = $this->get("/admin/login?lang={$code}")->assertOk()->getContent();

    expect($html)->toContain('lang="'.$code.'"')
        ->toContain('dir="'.Locales::dir($code).'"')
        ->toContain('hreflang="'.Locales::tag($code).'"')
        ->toContain(e(__('filament-panels::auth/pages/login.form.email.label', [], $code)));

    // Kept for the rest of the visit, without ?lang=.
    expect($this->get('/admin/login')->getContent())->toContain('lang="'.$code.'"');
})->with(['ar', 'en', 'fr', 'hi', 'ur-pk', 'zh-hans']);

it('renders the panel in the signed-in user’s language, and only theirs', function (): void {
    // Enrolled in 2FA, as anyone with publish rights must be before the panel opens.
    $french = staff('super_admin');
    $french->forceFill(['locale' => 'fr'])->save();
    $french->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $arabic = staff('super_admin');
    $arabic->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($french)->get('/admin/users')->assertOk()
        ->assertSee('lang="fr"', false)->assertSee(__('المستخدمون', [], 'fr'));

    $this->actingAs($arabic)->get('/admin/users')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);
    expect($arabic->fresh()->locale)->not->toBe('fr');
});

it('keeps the user’s language on Livewire updates, so validation errors are in it', function (): void {
    $user = staff('super_admin');
    $user->forceFill(['locale' => 'en'])->save();
    $this->actingAs($user);

    // A Livewire update re-runs only persistent middleware; the locale is one.
    expect(collect(app(\Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware::class)->getPersistentMiddleware()))
        ->toContain(\App\Http\Middleware\SetAdminLocale::class);

    app()->setLocale('en');
    livewire(CreateUser::class)->fillForm(['name' => '', 'email' => ''])->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertSee('field is required');
});

it('sends a notification in its recipient’s language, not the worker’s', function (): void {
    config()->set('mail.default', 'array');
    $editor = User::factory()->create(['locale' => 'fr']);
    $source = \App\Models\Source::factory()->create(['name' => 'Wire', 'consecutive_failures' => 5]);

    // The worker's locale is Arabic; the mail must still be French.
    app()->setLocale('ar');
    $editor->notify(new SourceDisabled($source));

    $sent = collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage()->getSubject());
    expect($sent->all())->toBe([__('توقّف مصدر: :name', ['name' => 'Wire'], 'fr')])
        ->and(preg_match('/\p{Arabic}/u', $sent->first()))->toBe(0)
        ->and(app()->getLocale())->toBe('ar');
});

it('presents an audit entry in the panel language without changing what was stored', function (): void {
    $article = \App\Models\Article::factory()->create();
    $entry = activity('article.status')->performedOn($article)
        ->withProperties(['from' => 'idea', 'to' => 'assigned'])->log('مُسندة ← فكرة');
    $created = activity()->performedOn($article)->log('created');

    app()->setLocale('en');
    expect(\App\Support\AuditDescription::present($entry))->toBe(\App\Enums\ArticleStatus::Idea->label().' → '.\App\Enums\ArticleStatus::Assigned->label())
        ->and(\App\Support\AuditDescription::present($created))->toBe('Created');

    app()->setLocale('ar');
    expect(\App\Support\AuditDescription::present($entry))->toBe(\App\Enums\ArticleStatus::Assigned->label().' ← '.\App\Enums\ArticleStatus::Idea->label());

    // The evidence is untouched.
    expect($entry->fresh()->description)->toBe('مُسندة ← فكرة')->and($created->fresh()->description)->toBe('created');
});
