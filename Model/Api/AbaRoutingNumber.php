<?php

namespace Signifyd\Connect\Model\Api;

class AbaRoutingNumber
{
    /**
     * AbaRoutingNumber class should be extended/intercepted by plugin to add value to it.
     * The routing number (ABA) of the bank account that was used as provided during checkout.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}