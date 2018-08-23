<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = array('authorizenet_directpost');

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();

        if (is_object($responseXmlDocument)) {
            $cvvStatus = $responseXmlDocument->transaction->cardCodeResponse;
        }

        return (empty($cvvStatus) ? parent::getPaymentData($orderPayment) : $cvvStatus);
    }
}
