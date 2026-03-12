<?php

namespace App\Models;

use App\Constants\TenantSiteProvisioningStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantOnboardingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'brand_name',
        'username_slug',
        'completed_by_user_id',
        'onboarding_completed_at',
        'site_provisioning_status',
        'site_provisioning_error',
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'site_provisioning_status' => TenantSiteProvisioningStatus::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}