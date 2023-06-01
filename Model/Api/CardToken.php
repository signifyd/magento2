<?php

namespace Signifyd\Connect\Model\Api;

class CardToken
{
    /**
     * CardToken class should be extended/intercepted by plugin to add value to it.
     * A unique string value as provided by the cardTokenProvider
     * which replaces the card Primary Account Number (PAN).
     * The same cardToken from the same cardTokenProvider
     * should never be from two different PANs.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}