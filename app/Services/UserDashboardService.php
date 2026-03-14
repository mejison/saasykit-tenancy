<?php

namespace App\Services;

use App\Models\User;

class UserDashboardService
{
    public function __construct(
        private TenantOnboardingService $tenantOnboardingService,
    ) {}

    public function getUserDashboardUrl(User $user): string
    {
        if ($this->tenantOnboardingService->userRequiresOnboarding($user)) {
            return route('tenant-onboarding.show');
        }

        $tenant = $user->tenants()->orderByPivot('is_default', 'desc')->first();

        if ($tenant !== null) {
            return route('builder.redirect');
        }

        return route('home');
    }
}
