<?php

namespace App\Livewire\Checkout;

use App\Services\SessionService;
use App\Services\TenantCreationService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SubscriptionTenantPicker extends Component
{
    public $tenant;

    private SessionService $sessionService;

    private TenantCreationService $tenantCreationService;

    public function boot(SessionService $sessionService, TenantCreationService $tenantCreationService)
    {
        $this->sessionService = $sessionService;
        $this->tenantCreationService = $tenantCreationService;
    }

    public function mount()
    {
        $subscriptionCheckoutDto = $this->sessionService->getSubscriptionCheckoutDto();

        if (! empty($subscriptionCheckoutDto->tenantUuid)) {
            $this->tenant = $subscriptionCheckoutDto->tenantUuid;
        } else {
            $this->tenant = $this->tenantCreationService->findUserTenantsForNewSubscription(auth()->user())->first()?->uuid;
        }

        $subscriptionCheckoutDto->tenantUuid = $this->tenant;
        $this->sessionService->saveSubscriptionCheckoutDto($subscriptionCheckoutDto);
    }

    public function updatedTenant(string $value)
    {
        if (! empty($value)) {

            $tenant = $this->tenantCreationService->findUserTenantForNewSubscriptionByUuid(auth()->user(), $value);

            if ($tenant === null) {
                throw ValidationException::withMessages([
                    'tenant' => __('You do not have access to this account.'),
                ]);
            }
        }

        $subscriptionCheckoutDto = $this->sessionService->getSubscriptionCheckoutDto();
        $subscriptionCheckoutDto->tenantUuid = $value;
        $this->sessionService->saveSubscriptionCheckoutDto($subscriptionCheckoutDto);
    }

    public function render()
    {
        return view('livewire.checkout.subscription-tenant-picker', [
            'userTenants' => $this->tenantCreationService->findUserTenantsForNewSubscription(auth()->user()),
        ]);
    }
}
