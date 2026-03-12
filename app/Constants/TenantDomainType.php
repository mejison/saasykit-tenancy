<?php

namespace App\Constants;

enum TenantDomainType: string
{
    case PATH = 'path';
    case SUBDOMAIN = 'subdomain';
    case CUSTOM = 'custom';
}