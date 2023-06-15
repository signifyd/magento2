<?php

namespace Signifyd\Connect\Model\Api;

class Sellers
{
    /**
     * Sellers class should be extended/intercepted by plugin to add value to it.
     * Use only if you operate a marketplace (e.g. Ebay)
     * and allow other merchants to list and sell products on the online store.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
