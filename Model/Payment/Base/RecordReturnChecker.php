<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Signifyd\Connect\Api\RecordReturnCheckerInterface;

class RecordReturnChecker implements RecordReturnCheckerInterface
{
    /**
     * Invoke method.
     *
     * @param Order $order
     * @param Creditmemo $creditmemo
     * @return bool
     */
    public function __invoke(Order $order, Creditmemo $creditmemo)
    {
        return false;
    }
}
