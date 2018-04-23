<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class ExpMonthMapper extends DataMapper
{
    /**
     * Gets credit card expiration month on Magento's default location on database
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $expMonth = $orderPayment->getCcExpMonth();
        $expMonth = is_null($expMonth) ? '' : $expMonth;
        return $expMonth;
    }
}
