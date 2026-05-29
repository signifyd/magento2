<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['authorizenet_directpost'];

    /**
     * Gets payment CVV verification code.
     *
     * Returns the raw Authorize.net card code response (M, N, P, S, U, B).
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();
        $cvvStatus = null;

        if (is_object($responseXmlDocument)) {
            $cvvStatus = (string) $responseXmlDocument->transaction->cardCodeResponse;
            $cvvStatus = empty($cvvStatus) ? null : $cvvStatus;
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
