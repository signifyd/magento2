<?php

namespace Signifyd\Connect\Model\Payment\AdyenPayByLink;

use Signifyd\Connect\Model\Payment\Base\AsyncChecker as BaseAsyncChecker;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Model\Casedata;

class AsyncChecker extends BaseAsyncChecker
{
    public function __invoke(Order $order, Casedata $casedata)
    {
        // Perform checks specific to Adyen to determine if payment has been made.
        if ($order->getPayment()->getMethod() === 'adyen_pay_by_link' && !$order->getPayment()->getCcTransId()) {
            return false;
        }
        return parent::__invoke($order, $casedata);
    }
}


