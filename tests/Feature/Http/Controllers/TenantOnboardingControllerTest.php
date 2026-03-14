<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\ProvisionTenantSite;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\FeatureTest;

class TenantOnboardingControllerTest extends FeatureTest
{
    public function test_dashboard_redirects_to_onboarding_when_workspace_setup_is_incomplete(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('tenant-onboarding.show'));
    }

    public function test_authenticated_user_can_complete_workspace_onboarding(): void
    {
        config(['services.vvveb.public_host' => 'dev.beatkongs.com']);
        Bus::fake();

        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);

        $response = $this->actingAs($user)->post(route('tenant-onboarding.store'), [
            'brand_name' => 'Producer Empire',
            'username_slug' => 'producer-empire',
        ]);

        $response->assertRedirect(route('builder.redirect'));
        $this->assertDatabaseHas('tenant_onboarding_profiles', [
            'tenant_id' => $tenant->id,
            'brand_name' => 'Producer Empire',
            'username_slug' => 'producer-empire',
        ]);
        Bus::assertDispatched(ProvisionTenantSite::class);
    }
}
