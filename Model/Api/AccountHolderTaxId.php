<?php

namespace Signifyd\Connect\Model\Api;

class AccountHolderTaxId
{
    /**
     * AccountHolderTaxId class should be extended/intercepted by plugin to add value to it.
     * The unique taxpayer identifier for the account holder. Due to legal restrictions,
     * the only values currently accepted here are Brazilian CPF numbers. All other values provided will be rejected.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}