<?php

namespace App\Constants;

enum TenantDomainStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case VERIFICATION_PENDING = 'verification_pending';
    case FAILED = 'failed';
}