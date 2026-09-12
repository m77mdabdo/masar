<?php

declare(strict_types=1);

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(staff('editor_in_chief'));
});

it('renders the article list', function (): void {
    Article::factory()->count(3)->create();

    livewire(ListArticles::class)->assertOk();
});

it('renders the create form', function (): void {
    livewire(CreateArticle::class)->assertOk();
});

it('renders the edit form with every tab', function (): void {
    $article = publishableArticle();

    livewire(EditArticle::class, ['record' => $article->getKey()])
        ->assertOk()
        ->assertSchemaStateSet(['title' => $article->title]);
});

it('creates an article from the form', function (): void {
    $category = Category::factory()->create();

    livewire(CreateArticle::class)
        ->fillForm([
            'title' => 'ارتفاع حجم التداول في السوق الرئيسية',
            'slug' => 'tasi-record-volume',
            'category_id' => $category->id,
            'locale' => 'ar',
            'content_type' => 'news',
            'why_it_matters' => 'يعيد تعريف المنافسة في القطاع.',
            // A `simple()` repeater dehydrates to flat strings but hydrates
            // nested, so the fill shape and the stored shape differ.
            'summary' => [['point' => 'النقطة الأولى'], ['point' => 'النقطة الثانية']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::where('slug', 'tasi-record-volume')->first();

    expect($article)->not->toBeNull()
        ->and($article->title)->toBe('ارتفاع حجم التداول في السوق الرئيسية')
        ->and($article->status->value)->toBe('idea')
        // Stored flat, which is what the publish gate counts.
        ->and($article->summary)->toBe(['النقطة الأولى', 'النقطة الثانية']);
});

it('requires a title', function (): void {
    livewire(CreateArticle::class)
        ->fillForm(['title' => null, 'slug' => 'x'])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required']);
});

it('requires why_it_matters', function (): void {
    $category = Category::factory()->create();

    livewire(CreateArticle::class)
        ->fillForm([
            'title' => 'عنوان',
            'slug' => 'a-slug',
            'category_id' => $category->id,
            'why_it_matters' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['why_it_matters']);
});

it('rejects a slug that is not latin', function (): void {
    $category = Category::factory()->create();

    livewire(CreateArticle::class)
        ->fillForm([
            'title' => 'عنوان',
            'slug' => 'السعودية',
            'category_id' => $category->id,
            'why_it_matters' => 'مهم.',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('suggests a transliterated slug on demand', function (): void {
    $component = livewire(CreateArticle::class)
        ->fillForm(['title' => 'ارتفاع حجم التداول في السوق الرئيسية', 'locale' => 'ar'])
        ->callAction(TestAction::make('suggestSlug')->schemaComponent('slug'));

    // Raw state, not getState(): the latter validates the whole form, and this
    // test is only about the suggestion button.
    $slug = $component->get('data.slug');

    expect($slug)->toMatch('/^[a-z0-9-]+$/')
        ->not->toBeEmpty()
        ->not->toContain('%');
});

it('never exposes status as a form field', function (): void {
    // status is written only by TransitionArticleStatus.
    $article = publishableArticle();

    $form = livewire(EditArticle::class, ['record' => $article->getKey()])
        ->instance()->form;

    $fieldNames = array_keys($form->getFlatFields(withHidden: true));

    // The raw `data` array carries every model attribute, but only schema-backed
    // fields are written on save — so the question is whether a status *field*
    // exists, not whether the attribute is present.
    expect($fieldNames)->not->toContain('status')
        ->and($fieldNames)->not->toContain('published_at');
});

it('does not run a query per row in the table', function (): void {
    $category = Category::factory()->create();
    Article::factory()->count(3)->create(['category_id' => $category->id]);

    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    livewire(ListArticles::class)->assertOk();
    $few = $count;

    Article::factory()->count(12)->create(['category_id' => $category->id]);

    $count = 0;
    livewire(ListArticles::class)->assertOk();
    $many = $count;

    // Query count must not grow with the number of rows rendered.
    expect($many)->toBeLessThanOrEqual($few);
});

it('scopes the list to a writer\'s own work', function (): void {
    $writer = staff('writer');
    $own = Article::factory()->create(['author_id' => $writer->id]);
    $other = Article::factory()->create();

    $this->actingAs($writer);

    $ids = ArticleResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($own->id)->and($ids)->not->toContain($other->id);
});
