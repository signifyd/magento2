<?php

namespace Signifyd\Connect\Model\Api;

class SourceAccountDetails
{
    /**
     * SourceAccountDetails class should be extended/intercepted by plugin to add value to it.
     * These are details about the Payment Instrument
     * that are sourced directly from the institution that manages that instrument,
     * the issuing bank for example.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
