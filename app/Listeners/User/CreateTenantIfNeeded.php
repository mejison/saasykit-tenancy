<?php

namespace App\Listeners\User;

use App\Services\SessionService;
use App\Services\TenantCreationService;
use Illuminate\Auth\Events\Registered;

class CreateTenantIfNeeded
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private SessionService $sessionService,
        private TenantCreationService $tenantCreationService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if ($this->sessionService->shouldCreateTenantForFreePlanUser()) {
            $this->tenantCreationService->createTenantForFreePlanUser($event->user);
            $this->sessionService->resetCreateTenantForFreePlanUser();
        }
    }
}
