<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\ExpYearMapper as Base_ExpYearMapper;

class ExpYearMapper extends Base_ExpYearMapper
{
    protected $allowedMethods = array('authorizenet_directpost');

    /**
     * Gets expiry year from XML response from Authorize.net
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();

        if (is_object($responseXmlDocument)) {
            $expYear = $responseXmlDocument->transaction->payment->creditCard->expirationDate;
            $expYear = substr($expYear, -2);
        }

        $this->logger->debug('Expiry year found on payment mapper: ' . (empty($expYear) ? 'false' : $expYear));

        if (empty($expYear)) {
            $expYear = parent::getPaymentData($orderPayment);
        }

        return $expYear;
    }
}
