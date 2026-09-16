<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ads;

use App\Filament\Resources\Ads\Pages\ListAdCampaigns;
use App\Models\AdCampaign;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Campaigns, and a count. Deliberately not a marketplace.
 *
 * There is no targeting, no bidding and no reporting beyond impressions and
 * clicks, because there are no advertisers and will not be before handover.
 * What has to exist is the mechanism and the proof that paid placement is
 * marked — the label lives on the article side and is not optional there.
 */
class AdCampaignResource extends Resource
{
    protected static ?string $model = AdCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'المنتجات';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'حملة إعلانية';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الحملات الإعلانية';
    }

    public static function getNavigationLabel(): string
    {
        return 'الحملات الإعلانية';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('اسم الحملة')->required()->maxLength(255),

            TextInput::make('advertiser')
                ->label('الجهة المعلنة')
                ->required()
                ->maxLength(255)
                // Shown to the reader beside the ad, not merely recorded.
                ->helperText('يظهر للقارئ بجانب الإعلان. الإفصاح شرط لا يُتجاوز.'),

            DateTimePicker::make('starts_at')
                ->label('يبدأ')
                ->helperText('اتركه فارغًا ليبدأ فورًا.'),

            DateTimePicker::make('ends_at')
                ->label('ينتهي')
                ->helperText('اتركه فارغًا ليستمر حتى يُوقف يدويًا. بعد هذا التاريخ يتوقف العرض ويتعطّل الرابط.')
                ->after('starts_at'),

            Toggle::make('is_active')->label('نشطة'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('الحملة')->searchable(),
                TextColumn::make('advertiser')->label('الجهة المعلنة')->searchable(),

                IconColumn::make('running')
                    ->label('تعمل الآن')
                    // The answer the operator actually wants: not "is the flag
                    // on" but "is it being served right now", which depends on
                    // the dates as much as the flag.
                    ->state(fn (AdCampaign $record): bool => $record->isRunning())
                    ->boolean(),

                TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d')->placeholder('فورًا'),
                TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d')->placeholder('—'),

                TextColumn::make('creatives_count')->label('التصاميم')->counts('creatives'),
                TextColumn::make('impressions')
                    ->label('الظهور')
                    ->state(fn (AdCampaign $record): int => (int) $record->creatives()->sum('impressions'))
                    ->numeric(),
                TextColumn::make('clicks')
                    ->label('النقرات')
                    ->state(fn (AdCampaign $record): int => (int) $record->creatives()->sum('clicks'))
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make()->label('تحرير'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdCampaigns::route('/')];
    }
}
