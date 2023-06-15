<?php

namespace Signifyd\Connect\Model\Api;

class PaypalProtectionEligibility
{
    /**
     * PaypalProtectionEligibility class should be extended/intercepted by plugin to add value to it.
     * The response provided by Paypal for protection_eligibility.
     * The merchant protection level in effect for the transaction. Supported only for PayPal payments.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
