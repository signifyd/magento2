<?php

namespace Signifyd\Connect\Model\Payment\AdyenPayByLink;

use Signifyd\Connect\Model\Payment\Base\AsyncChecker as BaseAsyncChecker;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;

class AsyncChecker extends BaseAsyncChecker
{
    /**
     * Invoke method.
     *
     * @param Order $order
     * @param Casedata $case
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $case)
    {
        $retries = $case->getData('retries');

        if ($retries >= 5 || $order->getPayment()->getCcTransId()) {
            return true;
        }
        return false;
    }
}
