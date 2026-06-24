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
            // SEMANTIC colors (green/amber/red) so operator state still reads at a glance. The neutral chrome
            // is warmed from stock cold slate to the Stone palette so the whole surface reads warm-luxury,
            // not generic-SaaS — the dominant background reason the panel looked "not premium".
            //
            // The primary is a HAND-TUNED OKLCH copper ramp (hue 47°, chroma ~10% muted) anchored on Pantone
            // 8022 C at shade 500, NOT Color::hex('#A0715A'): the auto-generated palette over-saturated the
            // mid-shades into a loud "orange" button that read as generic SaaS, not the refined metallic copper
            // of the brand. The explicit ramp keeps the hue constant and the chroma restrained for a luxury read.
            ->colors([
                'primary' => [
                    50 => 'oklch(0.972 0.0074 47.02)',
                    100 => 'oklch(0.940 0.0161 47.02)',
                    200 => 'oklch(0.885 0.0285 47.02)',
                    300 => 'oklch(0.812 0.0409 47.02)',
                    400 => 'oklch(0.706 0.0533 47.02)',
                    500 => 'oklch(0.591 0.0619 47.02)',
                    600 => 'oklch(0.512 0.0601 47.02)',
                    700 => 'oklch(0.438 0.0526 47.02)',
                    800 => 'oklch(0.376 0.0434 47.02)',
                    900 => 'oklch(0.322 0.0372 47.02)',
                    950 => 'oklch(0.224 0.0260 47.02)',
                ],
                'gray' => Color::Stone,
            ])
            ->font('Instrument Sans')
            // Custom Filament theme (premium finishing): brand font + warm hairlines/shadows + branded
            // login backdrop. Compiled by Vite (resources/css/filament/admin/theme.css) — run `npm run build`.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->favicon(asset('images/brand/crcles-mark.png'))
            ->brandName('CRCLES')
            ->brandLogo(asset('images/brand/crcles-logo.png'))
            ->darkModeBrandLogo(asset('images/brand/crcles-logo-dark.png'))
            // The brand wordmark is now tight-cropped (glyph fills the frame), so it stays crisp and legible
            // at a real topbar height — the previous 1.75rem crushed a margin-heavy 4188x2492 canvas into an
            // illegible ~10px hairline, the single worst "not premium" signal (the asset itself was correct).
            ->brandLogoHeight('2.45rem')
            // Cmd/Ctrl+K type-to-jump global search — table-stakes for a premium console (resources opt in
            // via getGloballySearchableAttributes()).
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            // Discovery is repointed from the shell's default app/Filament/** into the OperatorPanel
            // module (ADR 2026-06-19): the operator surface IS OperatorPanel code, so its resources,
            // pages and widgets live under app/Modules/OperatorPanel/Filament/** — keeping the module
            // self-contained and setting the pattern for the seven module consoles still to come.
            ->discoverResources(in: app_path('Modules/OperatorPanel/Filament/Resources'), for: 'App\Modules\OperatorPanel\Filament\Resources')
            ->discoverPages(in: app_path('Modules/OperatorPanel/Filament/Pages'), for: 'App\Modules\OperatorPanel\Filament\Pages')
            ->discoverClusters(in: app_path('Modules/OperatorPanel/Filament/Clusters'), for: 'App\Modules\OperatorPanel\Filament\Clusters')
            // Dashboard analytics (operator-console UI pass, 2026-06-24): the default Account + Filament-info
            // widgets are gone — discovery now finds two real, module-scoped analytics in the Widgets namespace
            // (the CatalogPartiesOverview KPI band, then the MembershipsByStateChart, in discovery order).
            ->discoverWidgets(in: app_path('Modules/OperatorPanel/Filament/Widgets'), for: 'App\Modules\OperatorPanel\Filament\Widgets')
            ->pages([
                Dashboard::class,
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
