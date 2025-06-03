<?php

namespace Signifyd\Connect\Api;

use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;

interface AsyncCheckerInterface
{
    /**
     * Invoke method.
     *
     * @param Order $order
     * @param Casedata $case
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $case);
}
