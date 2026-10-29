<?php

declare(strict_types=1);

namespace App\Filament\Resources\Countries;

use App\Filament\Resources\Countries\Pages\CreateCountry;
use App\Filament\Resources\Countries\Pages\EditCountry;
use App\Filament\Resources\Countries\Pages\ListCountries;
use App\Filament\Support\TranslationsField;
use App\Models\Country;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('الكيانات');
    }

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('دولة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الدول');
    }

    public static function getNavigationLabel(): string
    {
        return __('الدول');
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

            TextInput::make('code')
                ->label(__('الرمز'))
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TranslationsField::make([
                TextInput::make('name')->label(__('الاسم'))->required(),
            ], columns: 2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('translations'))
            ->defaultSort('slug')
            ->columns([
                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->getStateUsing(fn (Country $record): ?string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('slug')->label(__('المسار'))->badge()->color('gray')->searchable(),
                TextColumn::make('code')->label(__('الرمز'))->badge()->placeholder('—'),
            ])
            ->recordActions([EditAction::make()->label(__('تحرير'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'edit' => EditCountry::route('/{record}/edit'),
        ];
    }
}
