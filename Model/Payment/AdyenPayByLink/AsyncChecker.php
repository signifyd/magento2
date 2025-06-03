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
     * @param Casedata $casedata
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $casedata)
    {
        if ($order->getPayment()->getMethod() === 'adyen_pay_by_link' && !$order->getPayment()->getCcTransId()) {
            return false;
        }
        return parent::__invoke($order, $casedata);
    }
}
