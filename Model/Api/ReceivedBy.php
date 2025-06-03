<?php

namespace Signifyd\Connect\Model\Api;

class ReceivedBy
{
    /**
     * ReceivedBy class should be extended/intercepted by plugin to add value to it.
     *
     * If the order was was placed on-behalf of a customer service or sales agent, his or her name.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
