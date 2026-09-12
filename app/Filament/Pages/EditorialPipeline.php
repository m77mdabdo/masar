<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\PublishGateFailed;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The board the editor-in-chief opens first every morning.
 *
 * Columns are the live workflow stages; cards are draggable only along edges the
 * transition map allows, and an illegal drop is answered with the reason rather
 * than snapping back without explanation.
 *
 * Every move goes through TransitionArticleStatus, so the board is subject to
 * the same authorisation, gate and audit as the edit screen.
 */
class EditorialPipeline extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.editorial-pipeline';

    /**
     * Terminal states are excluded: a board is for work in flight. Published,
     * archived and rejected articles are found through the table, not dragged.
     *
     * @var array<int, string>
     */
    private const COLUMNS = [
        'idea',
        'assigned',
        'research',
        'writing',
        'fact_check',
        'editor_review',
        'seo',
        'ready',
        'scheduled',
        'needs_revision',
        'on_hold',
    ];

    public static function getNavigationLabel(): string
    {
        return 'مسار التحرير';
    }

    public function getTitle(): string
    {
        return 'مسار التحرير';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }

    /**
     * @return array<int, ArticleStatus>
     */
    public function columns(): array
    {
        return array_map(
            static fn (string $value): ArticleStatus => ArticleStatus::from($value),
            self::COLUMNS,
        );
    }

    /**
     * Cards grouped by status.
     *
     * One query for the whole board with the listing eager-loads — never a query
     * per column.
     *
     * @return Collection<string, Collection<int, Article>>
     */
    public function board(): Collection
    {
        return ArticleResource::getEloquentQuery()
            ->whereIn('status', self::COLUMNS)
            ->with(['category:id,slug,color', 'category.translations', 'author:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (Article $article): string => $article->status->value);
    }

    /**
     * Which columns a given card may be dropped into, for the client to enforce
     * before a request is ever made.
     *
     * @return array<string, array<int, string>>
     */
    public function allowedMoves(): array
    {
        $map = [];

        foreach ($this->columns() as $status) {
            $map[$status->value] = collect($status->allowedTransitions())
                ->map(fn (ArticleStatus $s): string => $s->value)
                ->filter(fn (string $v): bool => in_array($v, self::COLUMNS, true))
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * Drop handler. The client already hides illegal targets; this re-checks,
     * because a board that trusts the browser is not a guard.
     */
    public function moveCard(int $articleId, string $target): void
    {
        $article = ArticleResource::getEloquentQuery()->find($articleId);

        if ($article === null) {
            Notification::make()->danger()->title('المادة غير موجودة')->send();

            return;
        }

        $to = ArticleStatus::tryFrom($target);

        if ($to === null) {
            Notification::make()->danger()->title('حالة غير معروفة')->send();

            return;
        }

        try {
            app(TransitionArticleStatus::class)($article, $to, auth()->user(), 'نُقلت من لوحة مسار التحرير');

            Notification::make()
                ->success()
                ->title("نُقلت إلى: {$to->label()}")
                ->send();
        } catch (InvalidStatusTransition $e) {
            Notification::make()->danger()->title('انتقال غير مسموح')->body($e->forEditor())->persistent()->send();
        } catch (PublishGateFailed $e) {
            Notification::make()->danger()->title('بوابة النشر رفضت المادة')->body(implode("\n", $e->reasons()))->persistent()->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('لا تملك صلاحية نقل هذه المادة')->send();
        }
    }

    public function editUrl(Article $article): string
    {
        return ArticleResource::getUrl('edit', ['record' => $article]);
    }
}
