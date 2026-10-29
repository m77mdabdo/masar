<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\TranslationsField;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('المحتوى');
    }

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('قسم');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الأقسام');
    }

    public static function getNavigationLabel(): string
    {
        return __('الأقسام');
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

            Select::make('parent_id')
                ->label(__('القسم الأب'))
                ->relationship('parent', 'slug')
                ->searchable()
                ->preload(),

            // The order is an editorial statement: 1-3 are the launch pillars.
            TextInput::make('sort_order')
                ->label(__('الترتيب'))
                ->numeric()
                ->default(0)
                ->required(),

            ColorPicker::make('color')->label(__('اللون')),

            Toggle::make('is_active')->label(__('مفعّل'))->default(true),

            TranslationsField::make([
                TextInput::make('name')->label(__('الاسم'))->required(),
                Textarea::make('description')->label(__('الوصف'))->rows(2),
                TextInput::make('meta_title')->label(__('عنوان الميتا')),
                TextInput::make('meta_description')->label(__('وصف الميتا')),
            ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('translations')->withCount('articles'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->getStateUsing(fn (Category $record): ?string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('slug')->label(__('المسار'))->badge()->color('gray'),
                TextColumn::make('sort_order')->label(__('الترتيب'))->sortable(),
                TextColumn::make('articles_count')->label(__('عدد المواد'))->numeric()->sortable(),
                IconColumn::make('is_active')->label(__('مفعّل'))->boolean(),
            ])
            ->recordActions([EditAction::make()->label(__('تحرير'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
