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

    protected static ?string $title = 'المحتوى المرتبط';

    public function form(Schema $schema): Schema
    {
        // Mentions are written by SyncArticleEntities from the article form,
        // never edited directly here.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('المحتوى المرتبط')
            ->description('كل ما نُشر وأشار إلى هذه الشركة، بترتيب النشر.')
            ->modifyQueryUsing(fn (Builder $query): Builder => EntityContentQuery::for($this->getOwnerRecord())
                ->applyTo($query))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('mentionable.title')
                    ->label('العنوان')
                    ->wrap()
                    ->limit(90)
                    ->url(fn (EntityMention $record): ?string => match (true) {
                        $record->mentionable instanceof Article => ArticleResource::getUrl('edit', ['record' => $record->mentionable]),
                        default => null,
                    }),

                TextColumn::make('mentionable_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'article' => 'مادة',
                        'opportunity' => 'فرصة',
                        default => $state,
                    }),

                TextColumn::make('role')
                    ->label('الدور')
                    ->badge()
                    ->formatStateUsing(fn (EntityRole $state): string => $state->label())
                    ->color(fn (EntityRole $state): string => match ($state) {
                        EntityRole::Primary => 'success',
                        EntityRole::Secondary => 'info',
                        EntityRole::Mentioned => 'gray',
                    }),

                TextColumn::make('prominence')->label('الأهمية')->numeric()->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ النشر')
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
