<?php

namespace Signifyd\Connect\Model\Api;

class Fingerprint
{
    /**
     * Fingerprint class should be extended/intercepted by plugin to add value to it.
     * deviceFingerprints field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
