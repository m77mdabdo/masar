<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnforceAdminSessionLifetime;
use App\Http\Middleware\EnsurePublishersUseTwoFactor;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            // Login only. Staff join by invitation — there is no self-registration
            // route, and adding one would let anyone into the newsroom.
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)

            /*
             * Two-factor is registered for everyone and *enforced* only for users
             * who can publish. `isRequired: true` makes Filament register the
             * set-up route and apply a gate middleware; the middleware itself is
             * swapped below for one that asks who the user is, because the panel
             * evaluates this flag at route-registration time, before any user
             * exists to test.
             */
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsurePublishersUseTwoFactor::class)

            ->colors([
                'primary' => Color::hex('#0E2A1C'),
                'gray' => Color::Stone,
                'success' => Color::hex('#1E5E3F'),
                'info' => Color::hex('#7FB69A'),
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName(fn (): string => app()->getLocale() === 'ar' ? __('مسار') : 'MASAR')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            // Initials drawn locally: the default fetched ui-avatars.com with each staff name.
            ->defaultAvatarProvider(\App\Filament\Support\InitialsAvatarProvider::class)
            ->favicon(asset('favicon.ico'))

            /*
             * The in-app notification centre. Database-backed, so a notice
             * survives the editor not being logged in when it was raised —
             * which is the case that matters: a source dies at 3am and the
             * person who can fix it arrives at 9.
             *
             * Polled rather than broadcast: there is no websocket server and
             * adding one to deliver a handful of notices a week would be
             * infrastructure with nothing behind it.
             */
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('المحتوى')),
                NavigationGroup::make(fn (): string => __('الكيانات')),
                NavigationGroup::make(fn (): string => __('المنتجات')),
                NavigationGroup::make(fn (): string => __('الجمهور')),
                NavigationGroup::make(fn (): string => __('النظام')),
            ])

            ->pages([Dashboard::class])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Before Authenticate, so the login page is in the visitor's
            // language too; once signed in it is the user's own. Persistent,
            // because Livewire's update requests (validation errors, action
            // notifications) only re-run persistent middleware: without it an
            // English editor's errors came back in Arabic.
            ->middleware([\App\Http\Middleware\SetAdminLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
                EnforceAdminSessionLifetime::class,
            ])
            ->renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): \Illuminate\Contracts\View\View => view('filament.auth.languages'))
            ->userMenuItems([
                \Filament\Actions\Action::make('language')
                    ->label(fn (): string => __('admin.language.menu').': '.\App\Support\Locales::name(app()->getLocale()))
                    ->icon('heroicon-o-language')
                    ->schema([
                        \Filament\Forms\Components\Select::make('locale')
                            ->label(fn (): string => __('admin.language.menu'))
                            ->options(fn (): array => collect(\App\Support\Locales::codes())
                                ->mapWithKeys(fn (string $c): array => [$c => \App\Support\Locales::name($c)])->all())
                            ->default(fn (): string => app()->getLocale())
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $code = (string) $data['locale'];
                        if (\App\Support\Locales::exists($code)) {
                            auth()->user()->forceFill(['locale' => $code])->save();
                        }
                        redirect(request()->header('referer') ?: filament()->getUrl());
                    }),
            ]);
    }
}
