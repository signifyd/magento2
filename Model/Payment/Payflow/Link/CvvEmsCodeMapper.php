<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool|mixed|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cvvStatus = $this->getSignifydPaymentData('PROCCVV2');

        if ($this->validate($cvvStatus) == false) {
            $cvvStatus = null;
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
