<?php

namespace App\Models;

use App\Constants\TenantSiteProvisioningStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider',
        'external_site_id',
        'external_site_uuid',
        'builder_url',
        'provisioning_status',
        'payload',
        'error_message',
        'provisioned_at',
        'last_synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'provisioning_status' => TenantSiteProvisioningStatus::class,
        'provisioned_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}