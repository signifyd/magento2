<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets payment CVV verification code.
     *
     * Returns the raw Payflow Link CVV response code from the PROCCVV2 field.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cvvStatus = $this->getSignifydPaymentData('PROCCVV2');
        $cvvStatus = empty($cvvStatus) ? null : $cvvStatus;

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
