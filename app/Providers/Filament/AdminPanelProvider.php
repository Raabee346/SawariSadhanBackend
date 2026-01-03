<?php

namespace App\Providers\Filament;

use Filament\Panel;
use App\Models\Admin;
use Filament\PanelProvider;
use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Log;
use Filament\Widgets\FilamentInfoWidget;
use App\Filament\Widgets\TotalUserWidget;
use Filament\Schemas\Components\Livewire;
use Filament\Http\Middleware\Authenticate;
use Filament\Support\Facades\FilamentView;
use App\Filament\Widgets\TotalVendorWidget;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();
        
        // Register custom Livewire component early to override Filament's default
        $this->app->afterResolving('livewire', function () {
            \Livewire\Livewire::component('filament.database-notifications', \App\Livewire\DatabaseNotifications::class);
        });
    }
    
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('admin')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([
                'Settings',
                'Vehicle Management',
                'Tax & Insurance'
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                //total user
                TotalUserWidget::class,
                //total vendor
                TotalVendorWidget::class,
                // AccountWidget::class,
                // FilamentInfoWidget::class,
                
            ])
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
            ]);
    }

    public function boot(): void
    {
        // No need to block deletions anymore - Filament's keepAfterClosed() handles it
        // Just monitor for logging purposes
        \DB::listen(function ($query) {
            if (str_contains($query->sql, 'notifications') && str_contains($query->sql, 'delete')) {
                \Log::debug('🔔 DELETE query on notifications table', [
                    'sql' => substr($query->sql, 0, 200),
                ]);
            }
        });
        
        // Simple FCM token capture script (runs on every authenticated page after login)
        FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn (): \Illuminate\Contracts\View\View => view('filament.hooks.admin-fcm-simple')
        );
        
        // Prevent notification auto-deletion - ensure notifications persist after clicking
        FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn (): \Illuminate\Contracts\View\View => view('filament.hooks.prevent-notification-deletion')
        );
    }
}
