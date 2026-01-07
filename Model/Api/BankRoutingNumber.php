<?php

namespace Signifyd\Connect\Model\Api;

class BankRoutingNumber
{
    /**
     * The routing number of the non-US bank account
     *
     * That was used for this transaction, such as a SWIFT code. If a US bank account, please use abaRoutingNumber
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
