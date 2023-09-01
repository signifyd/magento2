<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
{
    protected $allowedMethods = ['authorizenet_directpost'];

    /**
     * Gets expiry month from XML response from Authorize.net
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();

        if (is_object($responseXmlDocument)) {
            $expMonth = $responseXmlDocument->transaction->payment->creditCard->expirationDate;
            $expMonth = substr($expMonth, 0, 2);
        }

        $message = 'Expiry month found on payment mapper: ' . (empty($expMonth) ? 'false' : $expMonth);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expMonth)) {
            $expMonth = parent::getPaymentData($order);
        }

        return $expMonth;
    }
}
