<?php

namespace App\Services;

use App\Constants\TenantDomainStatus;
use App\Constants\TenantDomainType;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Validation\ValidationException;

class TenantDomainService
{
    public function __construct(
        private TenantDomainEntitlementService $tenantDomainEntitlementService,
    ) {}

    public function createDefaultDomainsForTenant(Tenant $tenant, string $usernameSlug): void
    {
        $host = $this->getPublicHost();

        if (! $host) {
            return;
        }

        $domain = TenantDomain::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'host' => $host,
                'path' => $usernameSlug,
            ],
            [
                'type' => TenantDomainType::PATH,
                'status' => TenantDomainStatus::ACTIVE,
                'source' => 'system',
                // Do not force this as primary here; `syncPrimarySystemDomain()` decides.
                'is_primary' => false,
                'verified_at' => now(),
            ],
        );

        $hasPrimary = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_primary', true)
            ->exists();

        if (! $hasPrimary) {
            $domain->update(['is_primary' => true]);
        }
    }

    public function syncEntitledDomains(Tenant $tenant): void
    {
        $tenant->loadMissing(['onboardingProfile', 'domains']);

        $usernameSlug = $tenant->onboardingProfile?->username_slug;

        if (blank($usernameSlug)) {
            return;
        }

        if ($this->tenantDomainEntitlementService->allowsPathDomain($tenant)) {
            $this->createDefaultDomainsForTenant($tenant, $usernameSlug);
        }

        if ($this->tenantDomainEntitlementService->allowsSubdomain($tenant)) {
            $this->createSubdomainForTenant($tenant, $usernameSlug);
        } else {
            $this->removeSystemSubdomain($tenant);
        }

        $this->syncPrimarySystemDomain($tenant);
    }

    public function createSubdomainForTenant(Tenant $tenant, string $usernameSlug): TenantDomain
    {
        $host = $this->buildSubdomainHost($usernameSlug);

        return TenantDomain::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'host' => $host,
                'path' => null,
            ],
            [

                'type' => TenantDomainType::SUBDOMAIN,
                'status' => TenantDomainStatus::ACTIVE,
                'source' => 'system',
                'is_primary' => false,
                'verified_at' => now(),
            ],
        );
    }

    public function addCustomDomain(Tenant $tenant, string $host): TenantDomain
    {
        $normalizedHost = $this->normalizeHost($host);

        $existingTenantDomain = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('host', $normalizedHost)
            ->whereNull('path')
            ->first();

        if ($existingTenantDomain) {
            return $existingTenantDomain;
        }

        if (! $this->tenantDomainEntitlementService->allowsCustomDomains($tenant)) {
            throw ValidationException::withMessages([
                'data.custom_domain' => __('Your current plan does not allow custom domains.'),
            ]);
        }

        if (! $this->canAddCustomDomain($tenant)) {
            throw ValidationException::withMessages([
                'data.custom_domain' => __('You have reached the custom domain limit for your current plan.'),
            ]);
        }

        if (blank($normalizedHost)) {
            throw ValidationException::withMessages([
                'data.custom_domain' => __('Please enter a valid domain.'),
            ]);
        }

        $existingDomain = TenantDomain::query()
            ->where('host', $normalizedHost)
            ->whereNull('path')
            ->where('tenant_id', '!=', $tenant->id)
            ->exists();

        if ($existingDomain) {
            throw ValidationException::withMessages([
                'data.custom_domain' => __('This domain is already attached to another workspace.'),
            ]);
        }

        return TenantDomain::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'host' => $normalizedHost,
                'path' => null,
            ],
            [
                'type' => TenantDomainType::CUSTOM,
                'status' => TenantDomainStatus::VERIFICATION_PENDING,
                'source' => 'user',
            ],
        );
    }

    public function setPrimaryDomain(Tenant $tenant, TenantDomain $domain): void
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);

        $tenant->domains()->update(['is_primary' => false]);

        // Avoid stale in-memory model state issues after bulk updates.
        TenantDomain::query()->whereKey($domain->id)->update(['is_primary' => true]);
    }

    public function removeCustomDomain(Tenant $tenant, TenantDomain $domain): void
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);

        if ($domain->type !== TenantDomainType::CUSTOM) {
            throw ValidationException::withMessages([
                'domain' => __('Only custom domains can be removed from this screen.'),
            ]);
        }

        $wasPrimary = $domain->is_primary;

        $domain->delete();

        if ($wasPrimary) {
            $tenant->domains()->where('type', '!=', TenantDomainType::CUSTOM->value)->latest()->first()?->update(['is_primary' => true]);
        }
    }

    public function getPrimaryDomain(Tenant $tenant): ?TenantDomain
    {
        return $tenant->domains()->where('is_primary', true)->first();
    }

    public function canAddCustomDomain(Tenant $tenant): bool
    {
        if (! $this->tenantDomainEntitlementService->allowsCustomDomains($tenant)) {
            return false;
        }

        return $tenant->domains()->where('type', TenantDomainType::CUSTOM->value)->count() < $this->tenantDomainEntitlementService->getMaxCustomDomains($tenant);
    }

    public function getPublicHost(): ?string
    {
        return config('services.vvveb.public_host')
            ?: parse_url(config('app.url'), PHP_URL_HOST)
            ?: null;
    }

    private function buildSubdomainHost(string $usernameSlug): string
    {
        return $usernameSlug.'.'.$this->getPublicHost();
    }

    private function removeSystemSubdomain(Tenant $tenant): void
    {
        $subdomain = $tenant->domains()->where('type', TenantDomainType::SUBDOMAIN->value)->first();

        if (! $subdomain) {
            return;
        }

        $wasPrimary = $subdomain->is_primary;

        $subdomain->delete();

        if ($wasPrimary) {
            $tenant->domains()->where('type', TenantDomainType::PATH->value)->first()?->update(['is_primary' => true]);
        }
    }

    private function syncPrimarySystemDomain(Tenant $tenant): void
    {
        $hasPrimaryCustomDomain = $tenant->domains()
            ->where('type', TenantDomainType::CUSTOM->value)
            ->where('is_primary', true)
            ->exists();

        if ($hasPrimaryCustomDomain) {
            return;
        }

        $preferredType = $this->tenantDomainEntitlementService->allowsSubdomain($tenant)
            ? TenantDomainType::SUBDOMAIN
            : TenantDomainType::PATH;

        $preferredDomain = $tenant->domains()->where('type', $preferredType->value)->first();

        if (! $preferredDomain) {
            return;
        }

        $tenant->domains()->update([
            'is_primary' => false,
        ]);

        TenantDomain::query()->whereKey($preferredDomain->id)->update(['is_primary' => true]);
    }

    private function normalizeHost(string $host): string
    {
        $normalizedHost = trim(strtolower($host));
        $normalizedHost = preg_replace('#^https?://#', '', $normalizedHost) ?? $normalizedHost;

        return trim($normalizedHost, '/');
    }
}
