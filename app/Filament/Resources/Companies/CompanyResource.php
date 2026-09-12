<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies;

use App\Enums\CompanyType;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\RelationManagers\MentionsRelationManager;
use App\Filament\Support\TranslationsField;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'الكيانات';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'شركة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الشركات';
    }

    public static function getNavigationLabel(): string
    {
        return 'الشركات';
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

            // A startup is a company with type = startup, never its own table.
            Select::make('type')
                ->label('النوع')
                ->options(CompanyType::options())
                ->default(CompanyType::Company->value)
                ->required(),

            Select::make('industry_id')
                ->label('القطاع')
                ->relationship('industry', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($r): string => $r->name ?? $r->slug)
                ->searchable()
                ->preload(),

            Select::make('country_id')
                ->label('الدولة')
                ->relationship('country', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($r): string => $r->name ?? $r->slug)
                ->searchable()
                ->preload(),

            TextInput::make('founded_year')->label('سنة التأسيس')->numeric()->minValue(1800)->maxValue((int) date('Y')),

            TextInput::make('website')
                ->label('الموقع')
                ->url()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TextInput::make('ticker')
                ->label('الرمز في السوق')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            FileUpload::make('logo_path')
                ->label('الشعار')
                ->image()
                ->disk('public')
                ->directory('companies')
                ->acceptedFileTypes(config('masar.media.accepted')),

            Toggle::make('is_verified')
                ->label('موثّقة')
                ->helperText('فعّلها بعد التحقق من البيانات من مصدر رسمي.'),

            TranslationsField::make([
                TextInput::make('name')->label('الاسم')->required(),
                TextInput::make('short_description')->label('وصف مختصر'),
                Textarea::make('description')->label('الوصف')->rows(3),
            ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'translations', 'industry.translations', 'country.translations',
            ]))
            ->defaultSort('mentions_count', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->getStateUsing(fn (Company $r): ?string => $r->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (CompanyType $s): string => $s->label()),
                TextColumn::make('industry.name')
                    ->label('القطاع')
                    ->getStateUsing(fn (Company $r): ?string => $r->industry?->name)
                    ->placeholder('—'),
                TextColumn::make('ticker')
                    ->label('الرمز')
                    ->badge()
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'masar-ltr']),
                TextColumn::make('mentions_count')->label('الإشارات')->numeric()->sortable(),
                IconColumn::make('is_verified')->label('موثّقة')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('النوع')->options(CompanyType::options())->multiple(),
                SelectFilter::make('industry')->label('القطاع')->relationship('industry', 'slug')->searchable()->preload(),
                SelectFilter::make('country')->label('الدولة')->relationship('country', 'slug')->searchable()->preload(),
                TernaryFilter::make('is_verified')->label('موثّقة'),
            ])
            ->recordActions([EditAction::make()->label('تحرير')]);
    }

    public static function getRelations(): array
    {
        return [
            MentionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
