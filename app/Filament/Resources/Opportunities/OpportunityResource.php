<?php

declare(strict_types=1);

namespace App\Filament\Resources\Opportunities;

use App\Enums\OpportunityPotential;
use App\Filament\Resources\Opportunities\Pages\CreateOpportunity;
use App\Filament\Resources\Opportunities\Pages\EditOpportunity;
use App\Filament\Resources\Opportunities\Pages\ListOpportunities;
use App\Models\Opportunity;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('المنتجات');
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('فرصة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الفرص');
    }

    public static function getNavigationLabel(): string
    {
        return __('الفرص');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('العنوان'))->required()->columnSpanFull(),

            TextInput::make('slug')
                ->label(__('المسار'))
                ->required()
                ->rules(['regex:/^[a-z0-9-]+$/'])
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            Select::make('locale')
                ->label(__('اللغة'))
                ->options(fn (): array => collect(config('masar.locales'))->map(fn (array $l): string => $l['name'])->all())
                ->default(config('masar.default_locale'))
                ->required(),

            Textarea::make('summary')->label(__('الملخص'))->rows(3)->required()->columnSpanFull(),

            Select::make('opportunity_type')
                ->label(__('النوع'))
                ->options([
                    'tender' => __('مناقصة'),
                    'licence' => __('ترخيص'),
                    'funding' => __('تمويل'),
                    'partnership' => __('شراكة'),
                    'market_entry' => __('دخول سوق'),
                    'concession' => __('امتياز'),
                ])
                ->required(),

            Select::make('potential')
                ->label(__('حجم الفرصة'))
                ->options(OpportunityPotential::options())
                ->default(OpportunityPotential::Medium->value)
                ->required()
                ->helperText(__('تقدير تحريري. تضخيمه يُفقد بقية الفرص قيمتها.')),

            Select::make('industry_id')
                ->label(__('القطاع'))
                ->relationship('industry', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name ?? $record->slug)
                ->searchable()->preload(),

            Select::make('country_id')
                ->label(__('الدولة'))
                ->relationship('country', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name ?? $record->slug)
                ->searchable()->preload(),

            DatePicker::make('deadline')->label(__('آخر موعد')),

            Select::make('status')
                ->label(__('الحالة'))
                ->options(['open' => __('مفتوحة'), 'closed' => __('مغلقة')])
                ->default('open')
                ->required(),

            /*
             * An opportunity without a verifiable official source is a rumour,
             * and MASAR does not publish rumours.
             */
            TextInput::make('official_source_url')
                ->label(__('المصدر الرسمي'))
                ->url()
                ->required()
                ->helperText(__('رابط رسمي يمكن التحقق منه. بدونه هذه إشاعة، لا فرصة.'))
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),

            Repeater::make('requirements')
                ->label(__('المتطلبات'))
                ->simple(TextInput::make('requirement')->label(__('متطلب'))->required())
                ->defaultItems(0)
                ->addActionLabel(__('أضف متطلبًا'))
                ->columnSpanFull(),

            DateTimePicker::make('published_at')->label(__('تاريخ النشر'))->seconds(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['industry.translations', 'country.translations']))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('العنوان'))->searchable()->wrap()->limit(80),
                TextColumn::make('potential')
                    ->label(__('الحجم'))
                    ->badge()
                    ->formatStateUsing(fn (OpportunityPotential $state): string => $state->label())
                    // Brand token → Filament palette slot.
                    ->color(fn (OpportunityPotential $state): string => match ($state->colour()) {
                        'mint' => 'success',
                        'gold' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('opportunity_type')->label(__('النوع'))->badge(),
                TextColumn::make('industry.name')
                    ->label(__('القطاع'))
                    ->getStateUsing(fn (Opportunity $record): ?string => $record->industry?->name)
                    ->placeholder('—'),
                TextColumn::make('deadline')
                    ->label(__('آخر موعد'))
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (Opportunity $record): ?string => $record->deadline?->isPast() ? 'danger' : null),
                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'open' ? __('مفتوحة') : __('مغلقة'))
                    ->color(fn (string $state): string => $state === 'open' ? 'success' : 'gray'),
                TextColumn::make('published_at')->label(__('النشر'))->dateTime('Y-m-d')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('potential')->label(__('الحجم'))->options(OpportunityPotential::options())->multiple(),
                SelectFilter::make('status')->label(__('الحالة'))->options(['open' => __('مفتوحة'), 'closed' => __('مغلقة')]),
                SelectFilter::make('industry')->label(__('القطاع'))->relationship('industry', 'slug')->searchable()->preload(),
                Filter::make('expiring')
                    ->label(__('ينتهي خلال 30 يومًا'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('deadline')
                        ->whereBetween('deadline', [now(), now()->addDays(30)])),
            ])
            ->recordActions([EditAction::make()->label(__('تحرير'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpportunities::route('/'),
            'create' => CreateOpportunity::route('/create'),
            'edit' => EditOpportunity::route('/{record}/edit'),
        ];
    }
}
