<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['adyen_cc','adyen_pay_by_link', 'adyen_applepay'];

    /**
     * Gets payment CVV verification code.
     *
     * Returns the raw Adyen numeric CVC result code (e.g. "1", "2", "3", "4", "5").
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $cvvStatus = null;

        if (isset($additionalInfo['adyen_cvc_result']) && empty($additionalInfo['adyen_cvc_result']) === false) {
            $parts = explode(" ", $additionalInfo['adyen_cvc_result']);
            $cvvStatus = array_shift($parts);
        } elseif (isset($additionalInfo['additionalData']['cvcResult']) &&
            empty($additionalInfo['additionalData']['cvcResult']) === false) {
            $parts = explode(" ", $additionalInfo['additionalData']['cvcResult']);
            $cvvStatus = $parts[0];
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
