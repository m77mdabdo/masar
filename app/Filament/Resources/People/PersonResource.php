<?php

declare(strict_types=1);

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Support\TranslationsField;
use App\Models\Person;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('الكيانات');
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('شخص');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الأشخاص');
    }

    public static function getNavigationLabel(): string
    {
        return __('الأشخاص');
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

            Select::make('company_id')
                ->label(__('الجهة'))
                ->relationship('company', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name ?? $record->slug)
                ->searchable()
                ->preload(),

            TextInput::make('linkedin')
                ->label('LinkedIn')
                ->url()
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            FileUpload::make('photo_path')
                ->label(__('الصورة'))
                ->image()
                ->disk('public')
                ->directory('people')
                ->maxSize((int) config('masar.media.max_upload_kb'))->acceptedFileTypes(config('masar.media.accepted'))->rules([new \App\Rules\SafeImage])
                ->acceptedFileTypes(config('masar.media.accepted')),

            /*
             * is_expert asserts that a real, identifiable person has agreed to be
             * quoted. Fabricating one is the single most damaging thing this
             * platform could publish, so the helper text says so plainly.
             */
            Toggle::make('is_expert')
                ->label(__('خبير'))
                ->helperText(__('فعّلها فقط لشخص حقيقي، معروف الهوية، وافق على الاقتباس منه.')),

            TranslationsField::make([
                TextInput::make('name')->label(__('الاسم'))->required(),
                TextInput::make('title')->label(__('الصفة')),
                Textarea::make('bio')->label(__('نبذة'))->rows(3),
            ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['translations', 'company.translations']))
            ->defaultSort('mentions_count', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->getStateUsing(fn (Person $record): ?string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $t) => $t->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('title')
                    ->label(__('الصفة'))
                    ->getStateUsing(fn (Person $record): ?string => $record->translate('title'))
                    ->placeholder('—'),
                TextColumn::make('company.name')
                    ->label(__('الجهة'))
                    ->getStateUsing(fn (Person $record): ?string => $record->company?->name)
                    ->placeholder('—'),
                TextColumn::make('mentions_count')->label(__('الإشارات'))->numeric()->sortable(),
                IconColumn::make('is_expert')->label(__('خبير'))->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_expert')->label(__('خبير')),
            ])
            ->recordActions([EditAction::make()->label(__('تحرير'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
