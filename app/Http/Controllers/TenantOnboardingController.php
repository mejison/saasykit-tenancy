<?php

namespace App\Http\Controllers;

use App\Services\TenantOnboardingService;
use App\Services\UserDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantOnboardingController extends Controller
{
    public function __construct(
        private TenantOnboardingService $tenantOnboardingService,
        private UserDashboardService $userDashboardService,
    ) {}

    public function show(): View|RedirectResponse
    {
        $user = auth()->user();
        $tenant = $this->tenantOnboardingService->getTenantRequiringOnboarding($user);

        if (! $tenant) {
            return redirect($this->userDashboardService->getUserDashboardUrl($user));
        }

        $profile = $this->tenantOnboardingService->getOrCreateProfile($tenant);

        return view('onboarding.tenant', [
            'tenant' => $tenant,
            'profile' => $profile,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenant = $this->tenantOnboardingService->getTenantRequiringOnboarding($user);

        if (! $tenant) {
            return redirect($this->userDashboardService->getUserDashboardUrl($user));
        }

        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'username_slug' => ['required', 'string', 'max:255'],
        ]);

        $this->tenantOnboardingService->completeOnboarding(
            tenant: $tenant,
            user: $user,
            brandName: $data['brand_name'],
            usernameSlug: $data['username_slug'],
        );

        return redirect($this->userDashboardService->getUserDashboardUrl($user))
            ->with('success', __('Your workspace is being prepared.'));
    }
}