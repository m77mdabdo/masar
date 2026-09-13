<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Redirects\NormalisePath;
use App\Actions\Redirects\ValidateRedirect;
use App\Models\NotFound;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The dead-URL list, worst first.
 *
 * A media site accumulates broken links faster than anyone notices: an old
 * newsletter, a reprinted citation, a slug someone changed last year. The point
 * of this page is that fixing one is a single click, not a trip to another
 * screen to retype a path that is already on this one.
 */
class NotFoundLog extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link-slash';

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'not-founds';

    protected string $view = 'filament.pages.not-found-log';

    public static function getNavigationLabel(): string
    {
        return 'روابط مكسورة';
    }

    public function getTitle(): string
    {
        return 'الروابط المكسورة (404)';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = NotFound::query()->unresolved()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => NotFound::query())
            ->defaultSort('hits', 'desc')
            ->emptyStateHeading('لا روابط مكسورة')
            ->emptyStateDescription('لم يصل أي زائر إلى صفحة غير موجودة بعد.')
            ->columns([
                TextColumn::make('path')
                    ->label('المسار')
                    ->searchable()
                    ->wrap()
                    ->extraAttributes(['class' => 'masar-ltr']),

                TextColumn::make('hits')
                    ->label('عدد الزيارات')
                    ->numeric()
                    ->sortable()
                    ->color(fn (NotFound $record): string => $record->hits >= 10 ? 'danger' : 'gray'),

                TextColumn::make('referrer')
                    ->label('المصدر')
                    ->placeholder('—')
                    ->limit(40)
                    ->extraAttributes(['class' => 'masar-ltr'])
                    ->toggleable(),

                TextColumn::make('last_seen_at')->label('آخر ظهور')->dateTime('Y-m-d H:i')->sortable(),

                IconColumn::make('resolved')->label('عولج')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('resolved')->label('عولج'),
            ])
            ->recordActions([
                $this->createRedirectAction(),
                $this->ignoreAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markResolved')
                        ->label('تعليم كمعالج')
                        ->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->forceFill(['resolved' => true])->each->save())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * One click from "this URL is dead" to "this URL works", with the source
     * path already filled in.
     */
    private function createRedirectAction(): Action
    {
        return Action::make('createRedirect')
            ->label('أنشئ تحويلًا')
            ->icon('heroicon-o-arrow-uturn-right')
            ->color('success')
            ->visible(fn (NotFound $record): bool => ! $record->resolved)
            ->fillForm(fn (NotFound $record): array => [
                'from_path' => $record->path,
                'status_code' => 301,
            ])
            ->schema([
                TextInput::make('from_path')
                    ->label('من')
                    ->required()
                    ->readOnly()
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                TextInput::make('to_path')
                    ->label('إلى')
                    ->required()
                    ->placeholder('/ar/...')
                    ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),
                Select::make('status_code')
                    ->label('النوع')
                    ->options([301 => '301 — دائم', 302 => '302 — مؤقت'])
                    ->default(301)
                    ->required(),
            ])
            ->action(function (NotFound $record, array $data): void {
                $normalise = app(NormalisePath::class);

                $from = $normalise($data['from_path']);
                $to = $normalise($data['to_path']);

                $problems = app(ValidateRedirect::class)($from, $to);

                if ($problems !== []) {
                    Notification::make()
                        ->danger()
                        ->title('تعذّر إنشاء التحويل')
                        ->body(implode("\n", $problems))
                        ->persistent()
                        ->send();

                    return;
                }

                Redirect::create([
                    'from_path' => $from,
                    'to_path' => $to,
                    'status_code' => (int) $data['status_code'],
                    'hits' => 0,
                ]);

                // Resolved only once the redirect actually exists.
                $record->forceFill(['resolved' => true])->save();

                Notification::make()->success()->title('أُنشئ التحويل')->send();
            });
    }

    private function ignoreAction(): Action
    {
        return Action::make('ignore')
            ->label('تجاهل')
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->visible(fn (NotFound $record): bool => ! $record->resolved)
            ->action(fn (NotFound $record) => $record->forceFill(['resolved' => true])->save());
    }
}
