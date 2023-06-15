<?php

namespace Signifyd\Connect\Model\Api;

class AccountLast4
{
    /**
     * AccountLast4 class should be extended/intercepted by plugin to add value to it.
     * The last 4 digits of the bank account number as provided during checkout.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
