<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    protected $allowedMethods = ['authorizenet_directpost'];

    /**
     * Gets last 4 credit card digits from XML response from Authorize.net
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();

        if (is_object($responseXmlDocument)) {
            $last4 = $responseXmlDocument->transaction->payment->creditCard->cardNumber;
            $last4 = substr($last4, -4);
        }

        $message = 'Last4 found on payment mapper: ' . (empty($last4) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($last4)) {
            $last4 = parent::getPaymentData($order);
        }

        return $last4;
    }
}
