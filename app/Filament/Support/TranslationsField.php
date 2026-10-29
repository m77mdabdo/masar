<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Locales;
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
            ->label(__('الترجمات'))
            ->relationship('translations')
            ->addActionLabel(__('أضف لغة'))
            ->defaultItems(1)
            ->minItems(1)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::localeName($state['locale'] ?? null))
            ->schema([
                Select::make('locale')
                    ->label(__('اللغة'))
                    ->options(fn (): array => collect(Locales::codes())
                        ->mapWithKeys(fn (string $code): array => [$code => self::localeName($code)])
                        ->all())
                    ->default(Locales::default())
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
        // The language's name in the panel's language ("Arabic", «الإنجليزية»),
        // not its native name: a field label is read by the editor, not the reader.
        return $locale === null ? null : __("admin.language.names.{$locale}");
    }
}
