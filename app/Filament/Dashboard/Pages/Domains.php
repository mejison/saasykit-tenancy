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
        return true;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Workspace');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-s-globe-alt';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        $tenant->loadMissing('onboardingProfile');

        $status = (string) ($tenant->onboardingProfile?->site_provisioning_status?->value ?? '');

        return match ($status) {
            'failed' => __('Failed'),
            'pending' => __('Pending'),
            'processing' => __('Syncing'),
            default => null,
        };
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        $tenant->loadMissing('onboardingProfile');

        $status = (string) ($tenant->onboardingProfile?->site_provisioning_status?->value ?? '');

        return match ($status) {
            'failed' => 'danger',
            'pending', 'processing' => 'warning',
            default => null,
        };
    }
}