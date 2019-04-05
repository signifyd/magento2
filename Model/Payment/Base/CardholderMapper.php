<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class CardholderMapper extends DataMapper
{
    /**
     * Gets cardholder name on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cardholder = $order->getPayment()->getCcOwner();
        $cardholder = is_null($cardholder) ? '' : $cardholder;
        return $cardholder;
    }
}
