<?php

namespace App\Jobs;

use App\Services\VvvebProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionTenantSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
    ) {}

    public function handle(VvvebProvisioningService $vvvebProvisioningService): void
    {
        try {
            $vvvebProvisioningService->provisionTenantSite($this->tenantId);
        } catch (Throwable $throwable) {
            Log::error('Tenant site provisioning failed.', [
                'tenant_id' => $this->tenantId,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}