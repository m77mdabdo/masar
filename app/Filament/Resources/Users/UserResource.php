<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'مستخدم';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المستخدمون';
    }

    public static function getNavigationLabel(): string
    {
        return 'المستخدمون';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required(),

            TextInput::make('email')
                ->label('البريد')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'masar-ltr']),

            /*
             * Invitation, not self-registration: a new user is created with a
             * random password they never learn, and reaches the panel through
             * the password-reset flow. Nobody types a colleague's password.
             */
            TextInput::make('password')
                ->label('كلمة المرور')
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                ->helperText('اتركها فارغة لإنشاء كلمة مرور عشوائية ودعوة المستخدم عبر رابط استعادة.')
                ->visibleOn('create'),

            Select::make('roles')
                ->label('الأدوار')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->required()
                ->helperText('الصلاحيات تأتي من الأدوار. لا تُمنح فرديًا.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->copyable()
                    ->extraAttributes(['class' => 'masar-ltr']),
                TextColumn::make('roles.name')->label('الأدوار')->badge(),

                /*
                 * 2FA status is a column, not a detail page field: the question
                 * "who can publish without a second factor?" must be answerable
                 * at a glance.
                 */
                IconColumn::make('two_factor')
                    ->label('تحقق ثنائي')
                    ->getStateUsing(fn (User $r): bool => $r->hasTwoFactorEnabled())
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('success')
                    ->falseColor(fn (User $r): string => $r->requiresTwoFactor() ? 'danger' : 'gray')
                    ->tooltip(fn (User $r): string => $r->requiresTwoFactor() && ! $r->hasTwoFactorEnabled()
                        ? 'مطلوب: هذا المستخدم يملك صلاحية النشر'
                        : ''),
            ])
            ->filters([
                SelectFilter::make('roles')->label('الدور')->relationship('roles', 'name')->multiple()->preload(),
                TernaryFilter::make('two_factor')
                    ->label('تحقق ثنائي مفعّل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('app_authentication_secret'),
                        false: fn (Builder $query): Builder => $query->whereNull('app_authentication_secret'),
                    ),
            ])
            ->recordActions([EditAction::make()->label('تحرير')]);
    }

    /**
     * A password nobody chose and nobody needs to remember.
     */
    public static function randomPassword(): string
    {
        return Str::password(24);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
