<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Schemas\ArticleForm;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use App\Models\Article;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return 'مادة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المواد';
    }

    public static function getNavigationLabel(): string
    {
        return 'المواد';
    }

    /**
     * The count an editor-in-chief cares about is work in flight, not the
     * archive — a badge reading "12" is useful, one reading "48,000" is noise.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereNotIn('status', [
                ArticleStatus::Published->value,
                ArticleStatus::Archived->value,
                ArticleStatus::Rejected->value,
            ])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlesTable::configure($table);
    }

    /**
     * A writer sees their own work, not the newsroom's.
     *
     * Scoping here rather than in each page means the table, the record lookup
     * and every widget inherit it — there is no listing that forgets.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->can('article.update.any')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('author_id', $user->getKey())
                ->orWhereHas('coAuthors', fn (Builder $c) => $c->whereKey($user->getKey()));
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
