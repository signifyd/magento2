<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
{
    protected $allowedMethods = array('authorizenet_directpost');

    /**
     * Gets expiry month from XML response from Authorize.net
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();

        if (is_object($responseXmlDocument)) {
            $expMonth = $responseXmlDocument->transaction->payment->creditCard->expirationDate;
            $expMonth = substr($expMonth, 0, 2);
        }

        $this->logger->debug('Expiry month found on payment mapper: ' . (empty($expMonth) ? 'false' : $expMonth));

        if (empty($expMonth)) {
            $expMonth = parent::getPaymentData($orderPayment);
        }

        return $expMonth;
    }
}
