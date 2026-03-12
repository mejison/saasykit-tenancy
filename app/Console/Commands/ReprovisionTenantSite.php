<?php

namespace App\Console\Commands;

use App\Constants\TenantSiteProvisioningStatus;
use App\Models\Tenant;
use App\Models\TenantOnboardingProfile;
use App\Services\VvvebProvisioningService;
use Illuminate\Console\Command;
use Throwable;

class ReprovisionTenantSite extends Command
{
    protected $signature = 'app:reprovision-tenant-site
        {tenant? : Tenant id or onboarding slug}
        {--all-failed : Reprovision all tenants with failed site provisioning}
        {--force : Re-run even if the tenant is already provisioned}';

    protected $description = 'Retries BeatKongs Vvveb site provisioning for a tenant or all failed tenants.';

    public function handle(VvvebProvisioningService $vvvebProvisioningService): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->error('No tenants matched the provided criteria.');

            return self::FAILURE;
        }

        $successful = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $site = $vvvebProvisioningService->provisionTenantSite($tenant->id, (bool) $this->option('force'));

                $successful++;

                $this->info(sprintf(
                    'Tenant %d (%s) provisioned. external_site_id=%s',
                    $tenant->id,
                    $tenant->onboardingProfile?->username_slug ?? $tenant->uuid,
                    $site->external_site_id ?? 'pending'
                ));
            } catch (Throwable $throwable) {
                $failed++;

                $this->error(sprintf(
                    'Tenant %d (%s) failed: %s',
                    $tenant->id,
                    $tenant->onboardingProfile?->username_slug ?? $tenant->uuid,
                    $throwable->getMessage()
                ));
            }
        }

        $this->newLine();
        $this->line(sprintf('Completed. success=%d failed=%d', $successful, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveTenants()
    {
        if ($this->option('all-failed')) {
            return Tenant::query()
                ->whereHas('onboardingProfile', function ($query) {
                    $query->where('site_provisioning_status', TenantSiteProvisioningStatus::FAILED->value);
                })
                ->with('onboardingProfile')
                ->get();
        }

        $tenantIdentifier = $this->argument('tenant');

        if (! $tenantIdentifier) {
            $this->error('Provide a tenant id, onboarding slug, or use --all-failed.');

            return collect();
        }

        $tenant = is_numeric($tenantIdentifier)
            ? Tenant::query()->with('onboardingProfile')->find((int) $tenantIdentifier)
            : Tenant::query()
                ->whereHas('onboardingProfile', function ($query) use ($tenantIdentifier) {
                    $query->where('username_slug', $tenantIdentifier);
                })
                ->with('onboardingProfile')
                ->first();

        return $tenant ? collect([$tenant]) : collect();
    }
}