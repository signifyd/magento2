<?php

namespace Signifyd\Connect\Model\Api;

class PaypalPendingReasonCode
{
    /**
     * PaypalPendingReasonCode class should be extended/intercepted by plugin to add value to it.
     * The response provided in reason_code by Paypal if the payment_status is Pending.
     * This field does not apply to capturing point-of-sale authorizations,
     * which do not create pending payments.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}