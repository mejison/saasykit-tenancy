<?php

namespace App\Services;

use App\Constants\TenantSiteProvisioningStatus;
use App\Jobs\ProvisionTenantSite;
use App\Models\Tenant;
use App\Models\TenantOnboardingProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantOnboardingService
{
    public function __construct(
        private TenantDomainService $tenantDomainService,
    ) {}

    public function getOrCreateProfile(Tenant $tenant): TenantOnboardingProfile
    {
        return TenantOnboardingProfile::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['site_provisioning_status' => TenantSiteProvisioningStatus::DRAFT],
        );
    }

    public function userRequiresOnboarding(User $user): bool
    {
        return $this->getTenantRequiringOnboarding($user) !== null;
    }

    public function getTenantRequiringOnboarding(User $user): ?Tenant
    {
        return $this->getUserTenantsForOnboarding($user)->first();
    }

    public function getUserTenantsForOnboarding(User $user): Collection
    {
        return $user->tenants()
            ->with('onboardingProfile')
            ->orderByPivot('is_default', 'desc')
            ->get()
            ->filter(function (Tenant $tenant) {
                $profile = $tenant->onboardingProfile;

                return ! $profile
                    || ! $profile->onboarding_completed_at
                    || blank($profile->brand_name)
                    || blank($profile->username_slug);
            })
            ->values();
    }

    public function completeOnboarding(Tenant $tenant, User $user, string $brandName, string $usernameSlug): TenantOnboardingProfile
    {
        $normalizedSlug = Str::slug($usernameSlug);

        if (blank($normalizedSlug)) {
            throw ValidationException::withMessages([
                'username_slug' => __('Please provide a valid username slug.'),
            ]);
        }

        $this->assertUsernameSlugIsAvailable($tenant, $normalizedSlug);

        $profile = DB::transaction(function () use ($tenant, $user, $brandName, $normalizedSlug) {
            $profile = $this->getOrCreateProfile($tenant);

            $profile->update([
                'brand_name' => $brandName,
                'username_slug' => $normalizedSlug,
                'completed_by_user_id' => $user->id,
                'onboarding_completed_at' => now(),
                'site_provisioning_status' => TenantSiteProvisioningStatus::PENDING,
                'site_provisioning_error' => null,
            ]);

            $tenant->update([
                'name' => $brandName,
                'is_name_auto_generated' => false,
            ]);

            $this->tenantDomainService->syncEntitledDomains($tenant);

            return $profile->fresh();
        });

        ProvisionTenantSite::dispatch($tenant->id);

        return $profile;
    }

    private function assertUsernameSlugIsAvailable(Tenant $tenant, string $usernameSlug): void
    {
        $existingProfile = TenantOnboardingProfile::query()
            ->where('username_slug', $usernameSlug)
            ->where('tenant_id', '!=', $tenant->id)
            ->exists();

        if ($existingProfile) {
            throw ValidationException::withMessages([
                'username_slug' => __('This username slug is already in use.'),
            ]);
        }
    }
}