<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\TailAdminPalette;
use App\Filament\Widgets\LatestOrdersWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\NeedsActionWidget;
use App\Filament\Widgets\OrdersChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopProductsWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            // Staff can enable an authenticator app under Profile. Set ADMIN_REQUIRE_MFA=true to force it.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: (bool) config('app.admin_require_mfa'),
            )
            ->brandName('Funskool Admin')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            // TailAdmin design system: palette, Outfit font, 290px sidebar, header layout.
            // Styling lives in resources/css/filament/admin/theme.css (run `npm run build`).
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors(TailAdminPalette::colors())
            ->font('Outfit')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->darkMode(true)
            ->sidebarWidth('18.125rem')
            ->collapsedSidebarWidth('5.625rem')
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn () => '⌘K')
            ->databaseNotifications(position: DatabaseNotificationsPosition::Topbar)
            ->databaseNotificationsPolling('30s')
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn () => view('filament.components.theme-toggle'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('filament.components.sidebar-heading', ['label' => 'Menu']))
            ->navigationGroups([
                NavigationGroup::make('Sales')->icon(Heroicon::OutlinedShoppingBag),
                NavigationGroup::make('Catalog')->icon(Heroicon::OutlinedCube),
                NavigationGroup::make('Inventory')->icon(Heroicon::OutlinedArchiveBox),
                NavigationGroup::make('Customers')->icon(Heroicon::OutlinedUsers),
                NavigationGroup::make('Marketing')->icon(Heroicon::OutlinedMegaphone),
                NavigationGroup::make('Content')->icon(Heroicon::OutlinedDocumentText),
                NavigationGroup::make('Support')->icon(Heroicon::OutlinedLifebuoy),
                NavigationGroup::make('Integrations')->icon(Heroicon::OutlinedPuzzlePiece),
                NavigationGroup::make('Configuration')->icon(Heroicon::OutlinedCog6Tooth),
                NavigationGroup::make('Administration')->icon(Heroicon::OutlinedShieldCheck),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                NeedsActionWidget::class,
                StatsOverviewWidget::class,
                OrdersChartWidget::class,
                TopProductsWidget::class,
                LowStockWidget::class,
                LatestOrdersWidget::class,
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
