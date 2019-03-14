<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = array('payflow_link', 'payflow_advanced');

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return bool|mixed|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $cvvStatus = $this->getSignifydPaymentData('PROCCVV2');

        if ($this->validate($cvvStatus) == false) {
            $cvvStatus = NULL;
        }

        $this->logger->debug('CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus));

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($orderPayment);
        }

        return $cvvStatus;
    }
}
