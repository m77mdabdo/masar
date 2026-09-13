<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects;

use App\Actions\Redirects\NormalisePath;
use App\Actions\Redirects\ValidateRedirect;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return 'تحويل';
    }

    public static function getPluralModelLabel(): string
    {
        return 'التحويلات';
    }

    public static function getNavigationLabel(): string
    {
        return 'التحويلات';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')
                ->label('من')
                ->required()
                ->helperText('المسار القديم. يُوحَّد تلقائيًا: شرطة بادئة، بلا شرطة ختامية، بلا معاملات استعلام.')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->dehydrateStateUsing(fn (?string $state): string => app(NormalisePath::class)($state))
                ->rule(static function (Get $get, ?Redirect $record): Closure {
                    return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $problems = app(ValidateRedirect::class)(
                            (string) $value,
                            (string) $get('to_path'),
                            $record?->getKey(),
                        );

                        foreach ($problems as $problem) {
                            $fail($problem);
                        }
                    };
                })
                ->columnSpanFull(),

            TextInput::make('to_path')
                ->label('إلى')
                ->required()
                ->helperText('الوجهة الجديدة.')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr'])
                ->dehydrateStateUsing(fn (?string $state): string => app(NormalisePath::class)($state))
                ->live(onBlur: true)
                ->columnSpanFull(),

            Select::make('status_code')
                ->label('نوع التحويل')
                ->options([
                    301 => '301 — دائم',
                    302 => '302 — مؤقت',
                ])
                ->default(301)
                ->required()
                ->helperText('استخدم 301 عند تغيّر المسار نهائيًا؛ محركات البحث تنقل التقييم معه.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->columns([
                TextColumn::make('from_path')->label('من')->searchable()
                    ->extraAttributes(['class' => 'masar-ltr'])->wrap(),
                TextColumn::make('to_path')->label('إلى')->searchable()
                    ->extraAttributes(['class' => 'masar-ltr'])->wrap(),
                TextColumn::make('status_code')->label('النوع')->badge()
                    ->color(fn (int $state): string => $state === 301 ? 'success' : 'warning'),
                TextColumn::make('hits')->label('الاستخدامات')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('آخر استخدام')->dateTime('Y-m-d H:i')
                    ->placeholder('لم يُستخدم')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status_code')->label('النوع')->options([301 => '301', 302 => '302']),
            ])
            ->recordActions([
                EditAction::make()->label('تحرير'),
                DeleteAction::make()->label('حذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
