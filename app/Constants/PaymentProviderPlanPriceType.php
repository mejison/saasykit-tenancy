<?php

namespace App\Constants;

enum PaymentProviderPlanPriceType: string
{
    case MAIN_PRICE = 'main_price';
    case USAGE_BASED_PRICE = 'usage_based_price';
    case USAGE_BASED_FIXED_FEE_PRICE = 'usage_based_fixed_fee_price';
}
