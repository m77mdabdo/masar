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
            ->brandName('مسار')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.ico'))

            ->navigationGroups([
                NavigationGroup::make('المحتوى'),
                NavigationGroup::make('الكيانات'),
                NavigationGroup::make('المنتجات'),
                NavigationGroup::make('الجمهور'),
                NavigationGroup::make('النظام'),
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
            ->authMiddleware([
                Authenticate::class,
                EnforceAdminSessionLifetime::class,
            ]);
    }
}
