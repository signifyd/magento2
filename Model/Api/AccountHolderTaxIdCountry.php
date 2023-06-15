<?php

namespace Signifyd\Connect\Model\Api;

class AccountHolderTaxIdCountry
{
    /**
     * AccountHolderTaxIdCountry class should be extended/intercepted by plugin to add value to it.
     * The country that issued the holderTaxId. Due to legal restrictions,
     * the only value currently accepted here is BR.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
