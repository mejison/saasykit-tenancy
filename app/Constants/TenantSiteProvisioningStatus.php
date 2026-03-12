<?php

namespace App\Constants;

enum TenantSiteProvisioningStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PROVISIONED = 'provisioned';
    case FAILED = 'failed';
}