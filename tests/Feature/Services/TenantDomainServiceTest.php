<?php

namespace Tests\Feature\Services;

use App\Constants\SubscriptionStatus;
use App\Constants\TenantDomainMetadata;
use App\Constants\TenantDomainType;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\TenantDomainService;
use Tests\Feature\FeatureTest;

class TenantDomainServiceTest extends FeatureTest
{
    public function test_it_adds_custom_domain_for_tenant(): void
    {
        $tenant = $this->createTenant();
        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => true,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 1,
        ]);

        $tenantDomainService = app(TenantDomainService::class);

        $tenantDomainService->addCustomDomain($tenant, 'https://beats.example.com/');

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'beats.example.com',
            'path' => null,
            'type' => TenantDomainType::CUSTOM->value,
            'source' => 'user',
        ]);
    }

    public function test_it_can_switch_primary_domain(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);

        $tenant = $this->createTenant();
        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => true,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 1,
        ]);

        $tenantDomainService = app(TenantDomainService::class);

        $tenantDomainService->createDefaultDomainsForTenant($tenant, 'alpha');
        $customDomain = $tenantDomainService->addCustomDomain($tenant, 'alpha.com');

        $tenantDomainService->setPrimaryDomain($tenant, $customDomain);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'alpha.com',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'dev.beatkongs.com',
            'path' => 'alpha',
            'is_primary' => false,
        ]);
    }

    public function test_it_removes_custom_domain_and_restores_system_primary(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);

        $tenant = $this->createTenant();
        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => true,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 1,
        ]);

        $tenantDomainService = app(TenantDomainService::class);

        $tenantDomainService->createDefaultDomainsForTenant($tenant, 'beta');
        $customDomain = $tenantDomainService->addCustomDomain($tenant, 'beta.com');
        $tenantDomainService->setPrimaryDomain($tenant, $customDomain);

        $tenantDomainService->removeCustomDomain($tenant, $customDomain->fresh());

        $this->assertDatabaseMissing('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'beta.com',
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'dev.beatkongs.com',
            'path' => 'beta',
            'is_primary' => true,
        ]);
    }

    public function test_it_creates_subdomain_when_current_product_allows_it(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);

        $tenant = $this->createTenant();
        $tenant->onboardingProfile()->create([
            'brand_name' => 'Gamma',
            'username_slug' => 'gamma',
            'onboarding_completed_at' => now(),
            'site_provisioning_status' => 'draft',
        ]);

        $this->attachProductWithDomainMetadata($tenant, [
            'metadata' => [
                TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
                TenantDomainMetadata::SUBDOMAIN_ENABLED => true,
                TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
                TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
            ],
        ]);

        $tenantDomainService = app(TenantDomainService::class);
        $tenantDomainService->syncEntitledDomains($tenant);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'gamma.dev.beatkongs.com',
            'type' => TenantDomainType::SUBDOMAIN->value,
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'gamma.dev.beatkongs.com',
            'path' => null,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'dev.beatkongs.com',
            'path' => 'gamma',
            'is_primary' => false,
        ]);
    }

    public function test_it_preserves_custom_primary_domain_when_syncing_entitlements(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);

        $tenant = $this->createTenant();
        $tenant->onboardingProfile()->create([
            'brand_name' => 'Sigma',
            'username_slug' => 'sigma',
            'onboarding_completed_at' => now(),
            'site_provisioning_status' => 'draft',
        ]);

        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => true,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => true,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 1,
        ]);

        $tenantDomainService = app(TenantDomainService::class);
        $tenantDomainService->syncEntitledDomains($tenant);
        $customDomain = $tenantDomainService->addCustomDomain($tenant, 'sigma.com');
        $tenantDomainService->setPrimaryDomain($tenant, $customDomain);

        $tenantDomainService->syncEntitledDomains($tenant->fresh());

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'sigma.com',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'sigma.dev.beatkongs.com',
            'is_primary' => false,
        ]);
    }

    public function test_it_blocks_custom_domain_when_current_product_does_not_allow_it(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $tenant = $this->createTenant();
        $tenant->onboardingProfile()->create([
            'brand_name' => 'Delta',
            'username_slug' => 'delta',
            'onboarding_completed_at' => now(),
            'site_provisioning_status' => 'draft',
        ]);

        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
        ]);

        app(TenantDomainService::class)->addCustomDomain($tenant, 'blocked.example.com');
    }

    private function attachProductWithDomainMetadata($tenant, array $metadata): void
    {
        $product = Product::factory()->create([
            'metadata' => $metadata['metadata'] ?? $metadata,
        ]);

        $plan = Plan::factory()->create([
            'product_id' => $product->id,
        ]);

        Subscription::factory()->create([
            'plan_id' => $plan->id,
            'tenant_id' => $tenant->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'ends_at' => now()->addMonth(),
        ]);
    }
}