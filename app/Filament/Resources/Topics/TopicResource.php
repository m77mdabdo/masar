<?php

declare(strict_types=1);

namespace App\Filament\Resources\Topics;

use App\Actions\Articles\SyncArticleTopics;
use App\Filament\Resources\Topics\Pages\CreateTopic;
use App\Filament\Resources\Topics\Pages\EditTopic;
use App\Filament\Resources\Topics\Pages\ListTopics;
use App\Filament\Support\TranslationsField;
use App\Models\Topic;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class TopicResource extends Resource
{
    protected static ?string $model = Topic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('المحتوى');
    }

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('موضوع');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المواضيع');
    }

    public static function getNavigationLabel(): string
    {
        return __('المواضيع');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('المسار'))
                ->required()
                ->unique(ignoreRecord: true)
                ->rules(['regex:/^[a-z0-9-]+$/'])
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            Toggle::make('is_featured')->label(__('مميّز')),

            TranslationsField::make([
                TextInput::make('name')->label(__('الاسم'))->required(),
                Textarea::make('description')->label(__('الوصف'))->rows(2),
            ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('translations'))
            ->defaultSort('articles_count', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->getStateUsing(fn (Topic $record): ?string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('slug')->label(__('المسار'))->badge()->color('gray')->searchable(),
                TextColumn::make('articles_count')
                    ->label(__('المواد المنشورة'))
                    ->numeric()
                    ->sortable()
                    ->tooltip(__('يحتسب المواد المنشورة فقط.')),
                IconColumn::make('is_featured')->label(__('مميّز'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label(__('تحرير')),
                self::mergeAction(),
            ]);
    }

    /**
     * Merge this topic into another.
     *
     * Topics accumulate near-duplicates ("رؤية 2030" and "رؤية ٢٠٣٠") and the
     * fix has to move the articles, not just delete the loser — a deleted topic
     * takes its tagging with it and the surviving topic's count lies.
     */
    private static function mergeAction(): Action
    {
        return Action::make('merge')
            ->label(__('دمج'))
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('دمج الموضوع'))
            ->modalDescription(__('ستُنقل كل المواد إلى الموضوع الباقي، ويُحذف هذا الموضوع.'))
            ->schema([
                Select::make('survivor_id')
                    ->label(__('الموضوع الباقي'))
                    ->options(fn (Topic $record): array => Topic::query()
                        ->whereKeyNot($record->getKey())
                        ->with('translations')
                        ->get()
                        ->mapWithKeys(fn (Topic $t): array => [$t->id => $t->name ?? $t->slug])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Topic $record, array $data): void {
                $survivorId = (int) $data['survivor_id'];

                DB::transaction(function () use ($record, $survivorId): void {
                    // Move the tagging, skipping articles already on the survivor
                    // so the composite primary key is never violated.
                    $alreadyTagged = DB::table('article_topic')
                        ->where('topic_id', $survivorId)
                        ->pluck('article_id');

                    DB::table('article_topic')
                        ->where('topic_id', $record->getKey())
                        ->whereNotIn('article_id', $alreadyTagged)
                        ->update(['topic_id' => $survivorId]);

                    DB::table('article_topic')->where('topic_id', $record->getKey())->delete();

                    $record->delete();

                    app(SyncArticleTopics::class)->recount([$survivorId]);
                });

                Notification::make()->success()->title(__('تم الدمج'))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTopics::route('/'),
            'create' => CreateTopic::route('/create'),
            'edit' => EditTopic::route('/{record}/edit'),
        ];
    }
}
