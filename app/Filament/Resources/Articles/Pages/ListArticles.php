<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('مادة جديدة'),
        ];
    }

    /**
     * The shortcuts an editor uses hourly, as tabs rather than filter clicks.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),

            'mine' => Tab::make('موادي')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('author_id', auth()->id())),

            'in_progress' => Tab::make('قيد العمل')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('status', [
                    ArticleStatus::Published->value,
                    ArticleStatus::Archived->value,
                    ArticleStatus::Rejected->value,
                ])),

            'ready' => Tab::make('جاهزة')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ArticleStatus::Ready->value)),

            'scheduled' => Tab::make('مجدولة')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ArticleStatus::Scheduled->value)),

            'published' => Tab::make('منشورة')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ArticleStatus::Published->value)),
        ];
    }
}
