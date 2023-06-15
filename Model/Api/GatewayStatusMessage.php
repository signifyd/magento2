<?php

namespace Signifyd\Connect\Model\Api;

class GatewayStatusMessage
{
    /**
     * GatewayStatusMessage class should be extended/intercepted by plugin to add value to it.
     * Additional information provided by the payment provider
     * describing why the transaction succeeded or failed.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
