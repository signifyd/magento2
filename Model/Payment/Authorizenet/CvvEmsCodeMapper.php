<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = array('authorizenet_directpost');

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $responseXmlDocument = $this->getSignifydPaymentData();

        if (is_object($responseXmlDocument)) {
            $cvvStatus = $responseXmlDocument->transaction->cardCodeResponse;

            if ($cvvStatus == 'B') {
                $cvvStatus = 'U';
            }

            if ($this->validate($cvvStatus) == false) {
                $cvvStatus = NULL;
            }
        }

        $this->logger->debug('CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus), array('entity' => $order));

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
