<?php

namespace Signifyd\Connect\Model\Api;

class GatewayErrorCode
{
    /**
     * GatewayErrorCode class should be extended/intercepted by plugin to add value to it.
     * If the transaction resulted in an error or failure the enumerated reason
     * the transcaction failed as provided by the payment provider.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
