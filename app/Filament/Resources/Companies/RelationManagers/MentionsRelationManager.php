<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Enums\EntityRole;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\EntityMention;
use App\Queries\EntityContentQuery;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything published that mentions this company, across every content type.
 *
 * The listing is EntityContentQuery verbatim — the same single indexed query the
 * public company page will use — so if it is slow here it is slow there, and we
 * find out while building rather than after launch.
 */
class MentionsRelationManager extends RelationManager
{
    protected static string $relationship = 'mentions';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('المحتوى المرتبط');
    }

    public function form(Schema $schema): Schema
    {
        // Mentions are written by SyncArticleEntities from the article form,
        // never edited directly here.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('المحتوى المرتبط'))
            ->description(__('كل ما نُشر وأشار إلى هذه الشركة، بترتيب النشر.'))
            ->modifyQueryUsing(fn (Builder $query): Builder => EntityContentQuery::for($this->getOwnerRecord())
                ->applyTo($query))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('mentionable.title')
                    ->label(__('العنوان'))
                    ->wrap()
                    ->limit(90)
                    ->url(fn (EntityMention $record): ?string => match (true) {
                        $record->mentionable instanceof Article => ArticleResource::getUrl('edit', ['record' => $record->mentionable]),
                        default => null,
                    }),

                TextColumn::make('mentionable_type')
                    ->label(__('النوع'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'article' => __('مادة'),
                        'opportunity' => __('فرصة'),
                        default => $state,
                    }),

                TextColumn::make('role')
                    ->label(__('الدور'))
                    ->badge()
                    ->formatStateUsing(fn (EntityRole $state): string => $state->label())
                    ->color(fn (EntityRole $state): string => match ($state) {
                        EntityRole::Primary => 'success',
                        EntityRole::Secondary => 'info',
                        EntityRole::Mentioned => 'gray',
                    }),

                TextColumn::make('prominence')->label(__('الأهمية'))->numeric()->sortable(),

                TextColumn::make('created_at')
                    ->label(__('تاريخ النشر'))
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('article.view') ?? false;
    }
}
