<?php

namespace App\Modules\OperatorPanel\Providers;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->authGuard('operator')
            ->login()
            ->passwordReset()
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: false,
            )
            // Brand identity (CRCLES). Primary is Pantone 8022 C (#A0715A) — the brand's "bridge between
            // heritage and distinction", a metallic burgundy-gold sampled from CRURATED/NewCo/Branding. The
            // brand color drives panel chrome (buttons, links, active nav, focus rings); status badges keep
            // SEMANTIC colors (green/amber/red) so operator state still reads at a glance.
            ->colors([
                'primary' => Color::hex('#A0715A'),
            ])
            ->brandName('CRCLES')
            ->brandLogo(asset('images/brand/crcles-logo.png'))
            ->darkModeBrandLogo(asset('images/brand/crcles-logo-dark.png'))
            ->brandLogoHeight('1.75rem')
            ->sidebarCollapsibleOnDesktop()
            // Discovery is repointed from the shell's default app/Filament/** into the OperatorPanel
            // module (ADR 2026-06-19): the operator surface IS OperatorPanel code, so its resources,
            // pages and widgets live under app/Modules/OperatorPanel/Filament/** — keeping the module
            // self-contained and setting the pattern for the seven module consoles still to come.
            ->discoverResources(in: app_path('Modules/OperatorPanel/Filament/Resources'), for: 'App\Modules\OperatorPanel\Filament\Resources')
            ->discoverPages(in: app_path('Modules/OperatorPanel/Filament/Pages'), for: 'App\Modules\OperatorPanel\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Modules/OperatorPanel/Filament/Widgets'), for: 'App\Modules\OperatorPanel\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
