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

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('الكيانات');
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('شركة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الشركات');
    }

    public static function getNavigationLabel(): string
    {
        return __('الشركات');
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

            // A startup is a company with type = startup, never its own table.
            Select::make('type')
                ->label(__('النوع'))
                ->options(CompanyType::options())
                ->default(CompanyType::Company->value)
                ->required(),

            Select::make('industry_id')
                ->label(__('القطاع'))
                ->relationship('industry', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name ?? $record->slug)
                ->searchable()
                ->preload(),

            Select::make('country_id')
                ->label(__('الدولة'))
                ->relationship('country', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name ?? $record->slug)
                ->searchable()
                ->preload(),

            TextInput::make('founded_year')->label(__('سنة التأسيس'))->numeric()->minValue(1800)->maxValue((int) date('Y')),

            TextInput::make('website')
                ->label(__('الموقع'))
                ->url()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            TextInput::make('ticker')
                ->label(__('الرمز في السوق'))
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            FileUpload::make('logo_path')
                ->label(__('الشعار'))
                ->image()
                ->disk('public')
                ->directory('companies')
                ->maxSize((int) config('masar.media.max_upload_kb'))->acceptedFileTypes(config('masar.media.accepted'))->rules([new \App\Rules\SafeImage])
                ->acceptedFileTypes(config('masar.media.accepted')),

            Toggle::make('is_verified')
                ->label(__('موثّقة'))
                ->helperText(__('فعّلها بعد التحقق من البيانات من مصدر رسمي.')),

            TranslationsField::make([
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('short_description')->label(__('وصف مختصر')),
                Textarea::make('description')->label(__('الوصف'))->rows(3),
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
                    ->label(__('الاسم'))
                    ->getStateUsing(fn (Company $record): ?string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('type')
                    ->label(__('النوع'))
                    ->badge()
                    ->formatStateUsing(fn (CompanyType $state): string => $state->label()),
                TextColumn::make('industry.name')
                    ->label(__('القطاع'))
                    ->getStateUsing(fn (Company $record): ?string => $record->industry?->name)
                    ->placeholder('—'),
                TextColumn::make('ticker')
                    ->label(__('الرمز'))
                    ->badge()
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'masar-ltr']),
                TextColumn::make('mentions_count')->label(__('الإشارات'))->numeric()->sortable(),
                IconColumn::make('is_verified')->label(__('موثّقة'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('النوع'))->options(CompanyType::options())->multiple(),
                SelectFilter::make('industry')->label(__('القطاع'))->relationship('industry', 'slug')->searchable()->preload(),
                SelectFilter::make('country')->label(__('الدولة'))->relationship('country', 'slug')->searchable()->preload(),
                TernaryFilter::make('is_verified')->label(__('موثّقة')),
            ])
            ->recordActions([EditAction::make()->label(__('تحرير'))]);
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
