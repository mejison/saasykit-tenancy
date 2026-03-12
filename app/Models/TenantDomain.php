<?php

namespace App\Models;

use App\Constants\TenantDomainStatus;
use App\Constants\TenantDomainType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'host',
        'path',
        'type',
        'status',
        'source',
        'is_primary',
        'connected_to_provider_at',
        'verified_at',
        'ssl_status',
    ];

    protected $casts = [
        'type' => TenantDomainType::class,
        'status' => TenantDomainStatus::class,
        'is_primary' => 'boolean',
        'connected_to_provider_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}