<?php

namespace Signifyd\Connect\Model\Api;

class ParentTransactionId
{
    /**
     * ParentTransactionId class should be extended/intercepted by plugin to add value to it.
     * If there was a previous transaction for the payment like a partial AUTHORIZATION or SALE,
     * the parent id should include the originating transaction id.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
