<?php

namespace App\Http\Controllers;

use App\Services\TenantOnboardingService;
use App\Services\VvvebHandoffService;
use App\Services\VvvebProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BuilderRedirectController extends Controller
{
    public function __construct(
        private TenantOnboardingService $tenantOnboardingService,
        private VvvebProvisioningService $vvvebProvisioningService,
        private VvvebHandoffService $vvvebHandoffService,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('home');
        }

        if ($this->tenantOnboardingService->userRequiresOnboarding($user)) {
            return redirect()->route('tenant-onboarding.show');
        }

        $tenant = $user->tenants()->orderByPivot('is_default', 'desc')->first();

        if (! $tenant) {
            return redirect()->route('home');
        }

        try {
            $tenantSite = $this->vvvebProvisioningService->provisionTenantSite($tenant->id);
        } catch (Throwable $throwable) {
            return redirect()
                ->route('filament.dashboard.pages.domains', ['tenant' => $tenant])
                ->with('error', __('Failed to open the builder: :message', [
                    'message' => $throwable->getMessage(),
                ]));
        }

        $handoffUrl = $this->vvvebHandoffService->buildHandoffUrl(
            tenantUuid: (string) $tenant->uuid,
            siteId: (string) ($tenantSite->external_site_id ?? ''),
            userId: (int) $user->id,
            builderUrl: $tenantSite->builder_url ?: config('services.vvveb.builder_url'),
            nextQuery: 'module=settings/site',
        );

        if (! $handoffUrl) {
            return redirect()
                ->route('filament.dashboard.pages.domains', ['tenant' => $tenant])
                ->with('error', __('Builder handoff is not configured yet.'));
        }

        return redirect()->away($handoffUrl);
    }
}

