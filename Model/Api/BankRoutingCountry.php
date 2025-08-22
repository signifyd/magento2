<?php

namespace Signifyd\Connect\Model\Api;

class BankRoutingCountry
{
    /**
     * The country of origin of the bank account that was used for this transaction.
     * Country must be provided along with bankRoutingNumber. If you send a US ABA Number, this field is not required.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
