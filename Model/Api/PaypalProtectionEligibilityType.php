<?php

namespace Signifyd\Connect\Model\Api;

class PaypalProtectionEligibilityType
{
    /**
     * PaypalProtectionEligibilityType class should be extended/intercepted by plugin to add value to it.
     * The merchant protection type in effect for the transaction.
     * Returned only when protection_eligibility is ELIGIBLE or PARTIALLY_ELIGIBLE.
     * Supported only for PayPal payments.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}