<?php

namespace Signifyd\Connect\Model\Api;

class CardTokenProvider
{
    /**
     * CardTokenProvider class should be extended/intercepted by plugin to add value to it.
     * The issuer of the cardToken, that is, whomever generated the cardToken originally.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}