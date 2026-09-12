<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\EntityRole;
use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Market;
use App\Models\Person;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Illuminate\Database\Eloquent\Model;

/**
 * Entity tagging for the content graph.
 *
 * One repeater per entity kind rather than a single "add anything" list: an
 * editor thinks "which companies is this about", not "which polymorphic records
 * does this reference". Role and prominence sit on each row because ordering on
 * a company profile page depends on them.
 *
 * Nothing here writes to entity_mentions. The page collects this state and hands
 * it to SyncArticleEntities, which owns the table and the counters.
 */
class ArticleEntityFields
{
    /**
     * @var array<string, array{model: class-string<Model>, label: string, hint: string}>
     */
    public const KINDS = [
        'company' => [
            'model' => Company::class,
            'label' => 'الشركات والجهات',
            'hint' => 'الشركات التي تدور حولها المادة أو تتأثر بها.',
        ],
        'person' => [
            'model' => Person::class,
            'label' => 'الأشخاص',
            'hint' => 'أشخاص حقيقيون وردوا في المادة.',
        ],
        'country' => [
            'model' => Country::class,
            'label' => 'الدول',
            'hint' => 'السعودية أولًا، ثم الخليج، ثم ما يؤثر على السوق السعودية.',
        ],
        'industry' => [
            'model' => Industry::class,
            'label' => 'القطاعات',
            'hint' => '',
        ],
        'market' => [
            'model' => Market::class,
            'label' => 'الأسواق',
            'hint' => '',
        ],
    ];

    /**
     * @return array<int, Repeater>
     */
    public static function selectors(): array
    {
        return collect(self::KINDS)
            ->map(fn (array $config, string $kind): Repeater => self::repeater($kind, $config))
            ->values()
            ->all();
    }

    /**
     * @param  array{model: class-string<Model>, label: string, hint: string}  $config
     */
    private static function repeater(string $kind, array $config): Repeater
    {
        return Repeater::make("entities.{$kind}")
            ->label($config['label'])
            ->hint($config['hint'] ?: null)
            ->addActionLabel('أضف')
            ->defaultItems(0)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::optionLabel($config['model'], $state['id'] ?? null))
            ->schema([
                Select::make('id')
                    ->label('الكيان')
                    ->options(fn (): array => self::options($config['model']))
                    ->searchable()
                    ->required()
                    ->distinct()
                    ->columnSpan(2),

                Select::make('role')
                    ->label('الدور')
                    ->options(EntityRole::options())
                    ->default(EntityRole::Mentioned->value)
                    ->required(),

                /*
                 * Prominence drives ordering on the entity profile page: the
                 * pieces where a company *is* the story outrank the ones where
                 * it is mentioned in passing.
                 */
                Slider::make('prominence')
                    ->label('الأهمية')
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(5)
                    ->default(50),
            ])
            ->columns(4)
            ->columnSpanFull();
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, string>
     */
    private static function options(string $model): array
    {
        return $model::query()
            ->with('translations')
            ->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getKey() => $record->name ?? $record->slug,
            ])
            ->all();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function optionLabel(string $model, int|string|null $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $record = $model::query()->with('translations')->find($id);

        return $record?->name ?? $record?->slug;
    }
}
