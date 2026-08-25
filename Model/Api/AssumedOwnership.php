<?php

namespace Signifyd\Connect\Model\Api;

class AssumedOwnership
{
    /**
     * Construct a new AssumedOwnership object
     *
     * @param string $assumedOwner
     * @return []
     */
    public function __invoke($assumedOwner)
    {
        $assumedOwnership = [];
        $assumedOwnership['assumedOwner'] = $assumedOwner;

        return $assumedOwnership;
    }
}
