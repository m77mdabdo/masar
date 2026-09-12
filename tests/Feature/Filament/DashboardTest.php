<?php

declare(strict_types=1);

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\ScopedArticles;
use App\Filament\Widgets\BlockedByGate;
use App\Filament\Widgets\MostReadArticles;
use App\Filament\Widgets\PipelineOverview;
use App\Filament\Widgets\PublishedThisWeek;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\ScheduledNext48Hours;
use App\Models\Article;
use App\Models\User;
use Filament\Widgets\AccountWidget;

use function Pest\Livewire\livewire;

it('renders the dashboard', function (): void {
    $this->actingAs(staff('editor_in_chief'));

    livewire(Dashboard::class)->assertOk();
});

it('replaces the default filament widgets', function (): void {
    $this->actingAs(staff('editor_in_chief'));

    $widgets = livewire(Dashboard::class)->instance()->getWidgets();

    expect($widgets)->toBe([
        PipelineOverview::class,
        BlockedByGate::class,
        PublishedThisWeek::class,
        MostReadArticles::class,
        ScheduledNext48Hours::class,
        RecentActivity::class,
    ])->not->toContain(AccountWidget::class);
});

it('renders every widget', function (string $widget): void {
    $this->actingAs(staff('editor_in_chief'));

    Article::factory()->count(2)->create(['status' => ArticleStatus::Writing]);

    livewire($widget)->assertOk();
})->with([
    PipelineOverview::class,
    BlockedByGate::class,
    PublishedThisWeek::class,
    MostReadArticles::class,
    ScheduledNext48Hours::class,
    RecentActivity::class,
]);

it('shows a writer only their own work', function (): void {
    $writer = staff('writer');
    $own = Article::factory()->create(['author_id' => $writer->id, 'status' => ArticleStatus::Writing]);
    Article::factory()->count(4)->create(['status' => ArticleStatus::Writing]);

    $this->actingAs($writer);

    $ids = ScopedArticles::for($writer)->pluck('id');

    expect($ids->all())->toBe([$own->id])
        ->and(ScopedArticles::isNewsroomWide($writer))->toBeFalse();
});

it('shows an editor the whole newsroom', function (): void {
    $editor = staff('editor');
    Article::factory()->count(4)->create(['status' => ArticleStatus::Writing]);

    expect(ScopedArticles::for($editor)->count())->toBe(4)
        ->and(ScopedArticles::isNewsroomWide($editor))->toBeTrue();
});

it('lists only gate-blocked articles in the blocked widget', function (): void {
    $this->actingAs(staff('editor_in_chief'));

    $blocked = publishableArticle(['status' => ArticleStatus::Ready, 'summary' => null]);
    $clean = publishableArticle(['status' => ArticleStatus::Ready]);

    livewire(BlockedByGate::class)
        ->assertCanSeeTableRecords([$blocked])
        ->assertCanNotSeeTableRecords([$clean]);
});

it('lists only the next 48 hours in the scheduled widget', function (): void {
    $chief = staff('editor_in_chief');
    $this->actingAs($chief);

    $soon = publishableArticle(['status' => ArticleStatus::Scheduled, 'scheduled_for' => now()->addHours(6)]);
    $later = publishableArticle(['status' => ArticleStatus::Scheduled, 'scheduled_for' => now()->addDays(5)]);

    livewire(ScheduledNext48Hours::class)
        ->assertCanSeeTableRecords([$soon])
        ->assertCanNotSeeTableRecords([$later]);
});

it('counts only published articles from the last seven days', function (): void {
    $chief = staff('editor_in_chief');
    $this->actingAs($chief);

    Article::factory()->count(3)->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDays(2),
    ]);
    Article::factory()->create([
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDays(30),
    ]);

    // getCachedStats() is protected in Filament v4, so reach it reflectively
    // rather than asserting on rendered markup, which would break on restyling.
    $widget = livewire(PublishedThisWeek::class)->instance();
    $method = new ReflectionMethod($widget, 'getCachedStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    expect($stats[0]->getValue())->toBe('3');
});

it('hides every widget from a user who cannot view articles', function (): void {
    $outsider = User::factory()->create();
    $this->actingAs($outsider);

    foreach ([PipelineOverview::class, BlockedByGate::class, PublishedThisWeek::class,
        MostReadArticles::class, ScheduledNext48Hours::class, RecentActivity::class] as $widget) {
        expect($widget::canView())->toBeFalse();
    }
});

it('scopes the activity feed to articles the viewer can see', function (): void {
    $writer = staff('writer');
    $chief = staff('editor_in_chief');

    $own = Article::factory()->create(['author_id' => $writer->id, 'status' => ArticleStatus::Idea]);
    $other = Article::factory()->create(['status' => ArticleStatus::Idea]);

    $transition = app(TransitionArticleStatus::class);
    $transition($own, ArticleStatus::Assigned, $chief);
    $transition($other, ArticleStatus::Assigned, $chief);

    $this->actingAs($writer);

    $rows = livewire(RecentActivity::class)->instance()->getTable()->getQuery()->get();

    // An audit feed that leaks other people's articles is a permissions hole.
    expect($rows->pluck('subject_id')->unique()->all())->toBe([$own->id]);
});
