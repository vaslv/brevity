<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnsureTechnicalHost;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;

class MainPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('main')
            ->path('')
            ->login(Login::class)
            ->authGuard('web')
            ->colors([
                'primary' => Color::Teal,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                'web',
                // Admin panel is reachable on the technical host only; every
                // other short-link domain 404s before authentication.
                EnsureTechnicalHost::class,
                // Invalidate a user's other sessions when their password changes
                // (r41). Stock Filament ships this in the panel stack; the bare
                // 'web' group does not include it.
                AuthenticateSession::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): string => view('filament.version-chip')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => view('filament.version-chip')->render(),
            )
            // Registered after the TOPBAR_LOGO_AFTER version chip on purpose: the
            // plugin renders its menu at that same hook, and same-hook output
            // follows registration order, so this keeps the topbar layout
            // logo → version → menu.
            ->plugin(
                TopbarMenuPlugin::make()
                    ->resourceNavigationGroup(__('navigation.groups.system'))
                    ->resourceNavigationSort(100),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.browser-timezone')->render(),
            )
            // Dashboard-scoped: the geo map widget is lazy, so its own @assets
            // would arrive in a Livewire update that never executes module
            // scripts; the layout must carry the map bundle instead.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.clicks-geo-map-assets')->render(),
                scopes: Dashboard::class,
            );
    }
}
