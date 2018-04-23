<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class CardholderMapper extends DataMapper
{
    /**
     * Gets cardholder name on Magento's default location on database
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $cardholder = $orderPayment->getCcOwner();
        $cardholder = is_null($cardholder) ? '' : $cardholder;
        return $cardholder;
    }
}
