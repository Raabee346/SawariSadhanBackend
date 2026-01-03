<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class AdminFcmTokenWidget extends Widget
{
    protected string $view = 'filament.widgets.admin-fcm-token-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static bool $isLazy = false;
    
    // Hide this widget from the dashboard (it runs in background)
    public static function canView(): bool
    {
        return true; // Always run, but it's invisible
    }

    public function getViewData(): array
    {
        return [
            'adminId' => Auth::guard('admin')->id(),
        ];
    }
}

