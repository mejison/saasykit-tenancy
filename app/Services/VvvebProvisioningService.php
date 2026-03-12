<?php

namespace App\Services;

use App\Client\VvvebClient;
use App\Constants\TenantSiteProvisioningStatus;
use App\Models\Tenant;
use App\Models\TenantSite;
use RuntimeException;
use Throwable;

class VvvebProvisioningService
{
    public function __construct(
        private VvvebClient $vvvebClient,
        private TenantDomainService $tenantDomainService,
    ) {}

    public function provisionTenantSite(int $tenantId, bool $force = false): TenantSite
    {
        $tenant = Tenant::query()->with(['onboardingProfile', 'domains'])->findOrFail($tenantId);
        $profile = $tenant->onboardingProfile;

        if (! $profile || ! $profile->onboarding_completed_at) {
            throw new RuntimeException('Tenant onboarding is not completed yet.');
        }

        $this->tenantDomainService->syncEntitledDomains($tenant);
        $tenant->refresh()->load(['onboardingProfile', 'domains']);

        $tenantSite = TenantSite::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => 'vvveb',
            ],
            [
                'provisioning_status' => TenantSiteProvisioningStatus::PENDING,
            ],
        );

        if (! $force && $tenantSite->provisioning_status === TenantSiteProvisioningStatus::PROVISIONED && $tenantSite->external_site_id) {
            return $tenantSite;
        }

        $tenantSite->update([
            'provisioning_status' => TenantSiteProvisioningStatus::PROCESSING,
            'error_message' => null,
        ]);

        $primaryDomain = $tenant->domains->firstWhere('is_primary', true) ?? $tenant->domains->first();

        $payload = [
            'name' => $profile->brand_name,
            'slug' => $profile->username_slug,
            'tenant_uuid' => $tenant->uuid,
            'builder_url' => config('services.vvveb.builder_url'),
            'primary_domain' => $primaryDomain ? [
                'host' => $primaryDomain->host,
                'path' => $primaryDomain->path,
                'type' => $primaryDomain->type?->value,
            ] : null,
            'domains' => $tenant->domains->map(fn ($domain) => [
                'host' => $domain->host,
                'path' => $domain->path,
                'type' => $domain->type?->value,
                'is_primary' => $domain->is_primary,
            ])->values()->all(),
        ];

        try {
            $response = $this->vvvebClient->createSite($payload);
        } catch (Throwable $throwable) {
            $errorMessage = $throwable->getMessage();

            $tenantSite->update([
                'provisioning_status' => TenantSiteProvisioningStatus::FAILED,
                'payload' => $payload,
                'error_message' => $errorMessage,
            ]);

            $profile->update([
                'site_provisioning_status' => TenantSiteProvisioningStatus::FAILED,
                'site_provisioning_error' => $errorMessage,
            ]);

            throw new RuntimeException('Failed to provision Vvveb site: '.$errorMessage, previous: $throwable);
        }

        if (! $response->successful()) {
            $errorMessage = $response->json('message') ?: $response->body();

            $tenantSite->update([
                'provisioning_status' => TenantSiteProvisioningStatus::FAILED,
                'payload' => $payload,
                'error_message' => $errorMessage,
            ]);

            $profile->update([
                'site_provisioning_status' => TenantSiteProvisioningStatus::FAILED,
                'site_provisioning_error' => $errorMessage,
            ]);

            throw new RuntimeException('Failed to provision Vvveb site: '.$errorMessage);
        }

        $data = $response->json('data', $response->json());

        $tenantSite->update([
            'external_site_id' => data_get($data, 'site_id', data_get($data, 'id')),
            'external_site_uuid' => data_get($data, 'external_site_uuid', data_get($data, 'uuid')),
            'builder_url' => data_get($data, 'builder_url', config('services.vvveb.builder_url')),
            'provisioning_status' => TenantSiteProvisioningStatus::PROVISIONED,
            'payload' => $payload,
            'error_message' => null,
            'provisioned_at' => now(),
            'last_synced_at' => now(),
        ]);

        $profile->update([
            'site_provisioning_status' => TenantSiteProvisioningStatus::PROVISIONED,
            'site_provisioning_error' => null,
        ]);

        return $tenantSite->fresh();
    }
}