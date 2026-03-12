<?php

namespace Tests\Feature\Services;

use App\Constants\SubscriptionStatus;
use App\Constants\TenantDomainMetadata;
use App\Jobs\ProvisionTenantSite;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantOnboardingProfile;
use App\Services\TenantOnboardingService;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\FeatureTest;

class TenantOnboardingServiceTest extends FeatureTest
{
    public function test_complete_onboarding_updates_tenant_and_dispatches_provisioning_job(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);
        Bus::fake();

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => false,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
        ]);

        $tenantOnboardingService = app(TenantOnboardingService::class);

        $profile = $tenantOnboardingService->completeOnboarding($tenant, $user, 'Beat Kongs', 'beat-kongs');

        $this->assertInstanceOf(TenantOnboardingProfile::class, $profile);
        $this->assertEquals('Beat Kongs', $profile->brand_name);
        $this->assertEquals('beat-kongs', $profile->username_slug);
        $this->assertNotNull($profile->onboarding_completed_at);
        $this->assertEquals('Beat Kongs', $tenant->fresh()->name);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'dev.beatkongs.com',
            'path' => 'beat-kongs',
        ]);

        Bus::assertDispatched(ProvisionTenantSite::class, function (ProvisionTenantSite $job) use ($tenant) {
            return $job->tenantId === $tenant->id;
        });
    }

    public function test_complete_onboarding_uses_subdomain_as_primary_when_plan_allows_it(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);
        Bus::fake();

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $this->attachProductWithDomainMetadata($tenant, [
            TenantDomainMetadata::PATH_DOMAIN_ENABLED => true,
            TenantDomainMetadata::SUBDOMAIN_ENABLED => true,
            TenantDomainMetadata::CUSTOM_DOMAIN_ENABLED => false,
            TenantDomainMetadata::MAX_CUSTOM_DOMAINS => 0,
        ]);

        $tenantOnboardingService = app(TenantOnboardingService::class);

        $tenantOnboardingService->completeOnboarding($tenant, $user, 'Beat Kongs Pro', 'beat-kongs-pro');

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'beat-kongs-pro.dev.beatkongs.com',
            'path' => null,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'dev.beatkongs.com',
            'path' => 'beat-kongs-pro',
            'is_primary' => false,
        ]);
    }

    private function attachProductWithDomainMetadata($tenant, array $metadata): void
    {
        $product = Product::factory()->create([
            'metadata' => $metadata,
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