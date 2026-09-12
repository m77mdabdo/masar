<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

/**
 * The translations repeater shared by every structured-data resource.
 *
 * Structured data keeps one row and many translation rows so the entity stays a
 * single node in the content graph. That shape is identical for categories,
 * topics, companies, countries, industries and markets, so it is built once here
 * rather than copied six times.
 */
class TranslationsField
{
    /**
     * @param  array<int, Field>  $fields
     */
    public static function make(array $fields, int $columns = 1): Repeater
    {
        return Repeater::make('translations')
            ->label('الترجمات')
            ->relationship('translations')
            ->addActionLabel('أضف لغة')
            ->defaultItems(1)
            ->minItems(1)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::localeName($state['locale'] ?? null))
            ->schema([
                Select::make('locale')
                    ->label('اللغة')
                    ->options(fn (): array => collect(config('masar.locales'))
                        ->map(fn (array $l): string => $l['name'])
                        ->all())
                    ->default(config('masar.default_locale'))
                    ->required()
                    ->distinct()
                    ->live(),
                ...$fields,
            ])
            ->columns($columns)
            ->columnSpanFull();
    }

    private static function localeName(?string $locale): ?string
    {
        return $locale === null
            ? null
            : (config("masar.locales.{$locale}.name") ?? $locale);
    }
}
