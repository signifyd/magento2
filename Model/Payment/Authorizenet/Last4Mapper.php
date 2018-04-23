<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    protected $allowedMethods = array('authorizenet_directpost');

    /**
     * Gets last 4 credit card digits from XML response from Authorize.net
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();
        $last4 = $responseXmlDocument->transaction->payment->creditCard->cardNumber;
        $last4 = substr($last4, -4);
        return (empty($last4) ? parent::getPaymentData($orderPayment) : $last4);
    }
}
