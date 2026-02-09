<?php

namespace UnzerSDK\Resources\PaymentTypes;

use UnzerSDK\Traits\CanAuthorize;
use UnzerSDK\Traits\CanDirectCharge;

class Wero extends BasePaymentType
{
    use CanAuthorize;
    use CanDirectCharge;
}
