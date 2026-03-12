<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Domains extends Page
{
    protected string $view = 'filament.dashboard.pages.domains';

    public function getHeading(): string|Htmlable
    {
        return __('Domains & Site');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Domains & Site');
    }

    public static function canAccess(): bool
    {
        $tenantPermissionService = app(TenantPermissionService::class);

        return $tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}