<?php

declare(strict_types=1);

namespace App\Filament\Resources\Industries;

use App\Filament\Resources\Industries\Pages\CreateIndustry;
use App\Filament\Resources\Industries\Pages\EditIndustry;
use App\Filament\Resources\Industries\Pages\ListIndustries;
use App\Filament\Support\TranslationsField;
use App\Models\Industry;
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

class IndustryResource extends Resource
{
    protected static ?string $model = Industry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'الكيانات';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'قطاع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'القطاعات';
    }

    public static function getNavigationLabel(): string
    {
        return 'القطاعات';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label('المسار')
                ->required()
                ->unique(ignoreRecord: true)
                ->rules(['regex:/^[a-z0-9-]+$/'])
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TextInput::make('code')
                ->label('الرمز')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TranslationsField::make([
                TextInput::make('name')->label('الاسم')->required(),
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
                    ->label('الاسم')
                    ->getStateUsing(fn (Industry $r): ?string => $r->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('slug')->label('المسار')->badge()->color('gray')->searchable(),
                TextColumn::make('code')->label('الرمز')->badge()->placeholder('—'),
            ])
            ->recordActions([EditAction::make()->label('تحرير')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndustries::route('/'),
            'create' => CreateIndustry::route('/create'),
            'edit' => EditIndustry::route('/{record}/edit'),
        ];
    }
}
