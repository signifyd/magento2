<?php

namespace Signifyd\Connect\Model\Payment\AdyenKlarnaAccount;

use Signifyd\Connect\Model\Payment\Base\AsyncChecker as BaseAsyncChecker;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;

class AsyncChecker extends BaseAsyncChecker
{
    /**
     * @param Order $order
     * @param Casedata $casedata
     * @return bool|void
     */
    public function __invoke(Order $order, Casedata $casedata)
    {
        if ($order->getPayment()->getMethod() === 'adyen_klarna_account' && !$order->getPayment()->getCcTransId()) {
            return false;
        }

        return true;
    }
}
