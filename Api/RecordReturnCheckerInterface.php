<?php

namespace Signifyd\Connect\Api;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;

interface RecordReturnCheckerInterface
{
    /**
     * Invoke method.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    public function __invoke(Order $order, Creditmemo $creditmemo);
}
