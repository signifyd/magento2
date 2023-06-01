<?php

namespace Signifyd\Connect\Model\Api;

class AcquirerDetails
{
    /**
     * AcquirerDetails class should be extended/intercepted by plugin to add value to it.
     * Details about the merchant's acquiring bank.
     * Although this information is optional,
     * if it is not present it may result in missed SCA exemptions/exclusions.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}