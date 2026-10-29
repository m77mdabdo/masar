<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\MenuItem;
use Database\Seeders\EditionLabelsSeeder;
use Illuminate\Support\Facades\DB;

/*
 * The structured half of the graph in every edition. The seeder is additive:
 * it may add a language, never replace what an editor wrote.
 */
it('labels every section in every edition, and running it twice changes nothing', function (): void {
    $category = Category::factory()->create();
    DB::table('category_translations')->where('category_id', $category->id)->delete();
    DB::table('category_translations')->insert(['category_id' => $category->id, 'locale' => 'ar', 'name' => 'قصص نجاح']);

    (new EditionLabelsSeeder)->run();
    (new EditionLabelsSeeder)->run();

    expect(DB::table('category_translations')->where('category_id', $category->id)->pluck('name', 'locale')->all())
        ->toMatchArray(['ar' => 'قصص نجاح', 'en' => 'Success stories', 'ur-pk' => 'کامیابی کی کہانیاں', 'zh-hans' => '成功故事'])
        ->and(DB::table('category_translations')->where('category_id', $category->id)->count())->toBe(6);
});

it('never overwrites a label someone already wrote', function (): void {
    $item = MenuItem::factory()->create(['label' => ['ar' => 'قصص نجاح', 'en' => 'Stories']]);

    (new EditionLabelsSeeder)->run();

    expect($item->fresh()->label)->toMatchArray(['ar' => 'قصص نجاح', 'en' => 'Stories', 'fr' => 'Réussites']);
});

it('resolves a per-edition setting to the current edition and leaves other settings alone', function (): void {
    app()->setLocale('en');
    expect(setting('identity.tagline'))->toBe('From information to opportunity');

    app()->setLocale('ur-pk');
    expect(setting('identity.tagline'))->toBe('معلومات سے موقع تک');

    // A list is not a per-edition map.
    app(\App\Support\Settings::class)->set('market.ticker', [['label' => 'TASI']]);
    expect(setting('market.ticker'))->toBe([['label' => 'TASI']]);
});

it('marks a name that fell back to another edition with that edition’s lang and dir', function (): void {
    $company = \App\Models\Company::factory()->create();
    $company->translations()->delete();
    $company->translations()->create(['locale' => 'ar', 'name' => 'مرقاة <للتعليم>']);
    $company->load('translations');

    app()->setLocale('ar');
    expect((string) $company->displayName())->toBe('مرقاة &lt;للتعليم&gt;');

    app()->setLocale('en');
    expect((string) $company->displayName())->toBe('<bdi lang="ar" dir="rtl">مرقاة &lt;للتعليم&gt;</bdi>');
});

it('gives navigation the short label, replacing only the long one this seeder wrote itself', function (): void {
    $mine = MenuItem::factory()->create(['label' => ['ar' => 'عالمي بعدسة سعودية', 'fr' => 'Le monde, vu d’Arabie saoudite']]);
    $theirs = MenuItem::factory()->create(['label' => ['ar' => 'عالمي بعدسة سعودية', 'fr' => 'International']]);

    (new EditionLabelsSeeder)->run();

    expect($mine->fresh()->label['fr'])->toBe('Monde')
        ->and($theirs->fresh()->label['fr'])->toBe('International')
        ->and($mine->fresh()->label['zh-hans'])->toBe('全球');
});

it('marks Arabic-script text with its language on a page in another script', function (): void {
    app()->setLocale('en');
    expect(trim(view('components.ui.bidi', ['text' => 'مؤشر أ'])->render()))->toBe('<bdi lang="ar" dir="rtl">مؤشر أ</bdi>')
        ->and(trim(view('components.ui.bidi', ['text' => 'TASI'])->render()))->toBe('TASI');

    app()->setLocale('ur-pk');
    expect(trim(view('components.ui.bidi', ['text' => 'مؤشر أ'])->render()))->toBe('مؤشر أ');
});

it('keeps the brand MASAR in every edition', function (string $code): void {
    app()->setLocale($code);

    expect(setting('identity.site_name'))->toBe('MASAR');
    $this->get('/'.$code.'/about')->assertSee('| MASAR</title>', false);
})->with(['ar', 'en', 'ur-pk', 'zh-hans']);

it('stores each edition of a per-edition setting independently', function (): void {
    $settings = app(\App\Support\Settings::class);

    $settings->setEditions('identity.tagline', ['en' => 'Signals, not noise']);
    $settings->setEditions('identity.tagline', ['fr' => 'Des signaux, pas du bruit']);

    $tagline = $settings->editions('identity.tagline');
    expect($tagline['en'])->toBe('Signals, not noise')
        ->and($tagline['fr'])->toBe('Des signaux, pas du bruit')
        // Untouched editions keep their defaults.
        ->and($tagline['ar'])->toBe('من المعلومة إلى الفرصة');

    // Persisted, not only memoised.
    expect(\App\Models\Setting::query()->where('key', 'identity.tagline')->exists())->toBeTrue();
    app()->setLocale('fr');
    expect(app(\App\Support\Settings::class)->get('identity.tagline'))->toBe('Des signaux, pas du bruit');
});

it('reads a tagline stored before it was per edition as the Arabic one', function (): void {
    app(\App\Support\Settings::class)->set('identity.tagline', 'شعار قديم');

    expect(app(\App\Support\Settings::class)->editions('identity.tagline'))
        ->toMatchArray(['ar' => 'شعار قديم', 'en' => 'From information to opportunity']);
});

it('saves one edition from the settings page without overwriting another', function (): void {
    $settings = app(\App\Support\Settings::class);
    $settings->setEditions('identity.descriptor', ['ur-pk' => 'اردو وضاحت']);

    $this->actingAs(staff('super_admin'));
    \Pest\Livewire\livewire(\App\Filament\Pages\ManageSettings::class)
        ->set('data.identity__descriptor.en', 'Business · Markets')
        ->call('save');

    $saved = app(\App\Support\Settings::class)->editions('identity.descriptor');
    expect($saved['en'])->toBe('Business · Markets')
        ->and($saved['ur-pk'])->toBe('اردو وضاحت');
});
