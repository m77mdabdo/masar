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

    protected static string|UnitEnum|null $navigationGroup = 'المنتجات';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'فرصة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الفرص';
    }

    public static function getNavigationLabel(): string
    {
        return 'الفرص';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('العنوان')->required()->columnSpanFull(),

            TextInput::make('slug')
                ->label('المسار')
                ->required()
                ->rules(['regex:/^[a-z0-9-]+$/'])
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            Select::make('locale')
                ->label('اللغة')
                ->options(fn (): array => collect(config('masar.locales'))->map(fn (array $l): string => $l['name'])->all())
                ->default(config('masar.default_locale'))
                ->required(),

            Textarea::make('summary')->label('الملخص')->rows(3)->required()->columnSpanFull(),

            Select::make('opportunity_type')
                ->label('النوع')
                ->options([
                    'tender' => 'مناقصة',
                    'licence' => 'ترخيص',
                    'funding' => 'تمويل',
                    'partnership' => 'شراكة',
                    'market_entry' => 'دخول سوق',
                    'concession' => 'امتياز',
                ])
                ->required(),

            Select::make('potential')
                ->label('حجم الفرصة')
                ->options(OpportunityPotential::options())
                ->default(OpportunityPotential::Medium->value)
                ->required()
                ->helperText('تقدير تحريري. تضخيمه يُفقد بقية الفرص قيمتها.'),

            Select::make('industry_id')
                ->label('القطاع')
                ->relationship('industry', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($r): string => $r->name ?? $r->slug)
                ->searchable()->preload(),

            Select::make('country_id')
                ->label('الدولة')
                ->relationship('country', 'slug')
                ->getOptionLabelFromRecordUsing(fn ($r): string => $r->name ?? $r->slug)
                ->searchable()->preload(),

            DatePicker::make('deadline')->label('آخر موعد'),

            Select::make('status')
                ->label('الحالة')
                ->options(['open' => 'مفتوحة', 'closed' => 'مغلقة'])
                ->default('open')
                ->required(),

            /*
             * An opportunity without a verifiable official source is a rumour,
             * and MASAR does not publish rumours.
             */
            TextInput::make('official_source_url')
                ->label('المصدر الرسمي')
                ->url()
                ->required()
                ->helperText('رابط رسمي يمكن التحقق منه. بدونه هذه إشاعة، لا فرصة.')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->columnSpanFull(),

            Repeater::make('requirements')
                ->label('المتطلبات')
                ->simple(TextInput::make('requirement')->label('متطلب')->required())
                ->defaultItems(0)
                ->addActionLabel('أضف متطلبًا')
                ->columnSpanFull(),

            DateTimePicker::make('published_at')->label('تاريخ النشر')->seconds(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['industry.translations', 'country.translations']))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('العنوان')->searchable()->wrap()->limit(80),
                TextColumn::make('potential')
                    ->label('الحجم')
                    ->badge()
                    ->formatStateUsing(fn (OpportunityPotential $s): string => $s->label())
                    // Brand token → Filament palette slot.
                    ->color(fn (OpportunityPotential $s): string => match ($s->colour()) {
                        'mint' => 'success',
                        'gold' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('opportunity_type')->label('النوع')->badge(),
                TextColumn::make('industry.name')
                    ->label('القطاع')
                    ->getStateUsing(fn (Opportunity $r): ?string => $r->industry?->name)
                    ->placeholder('—'),
                TextColumn::make('deadline')
                    ->label('آخر موعد')
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (Opportunity $r): ?string => $r->deadline?->isPast() ? 'danger' : null),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $s): string => $s === 'open' ? 'مفتوحة' : 'مغلقة')
                    ->color(fn (string $s): string => $s === 'open' ? 'success' : 'gray'),
                TextColumn::make('published_at')->label('النشر')->dateTime('Y-m-d')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('potential')->label('الحجم')->options(OpportunityPotential::options())->multiple(),
                SelectFilter::make('status')->label('الحالة')->options(['open' => 'مفتوحة', 'closed' => 'مغلقة']),
                SelectFilter::make('industry')->label('القطاع')->relationship('industry', 'slug')->searchable()->preload(),
                Filter::make('expiring')
                    ->label('ينتهي خلال 30 يومًا')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('deadline')
                        ->whereBetween('deadline', [now(), now()->addDays(30)])),
            ])
            ->recordActions([EditAction::make()->label('تحرير')]);
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
