<?php

namespace Signifyd\Connect\Model\Api;

class Memberships
{
    /**
     * Memberships class should be extended/intercepted by plugin to add value to it.
     * The membership object should be used to indicate the usage of a rewards, discount,
     * or admission program by the buyer when they completed the checkout.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}