<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['adyen_cc','adyen_pay_by_link'];

    /**
     * Gets payment AVS verification code.
     *
     * Returns the raw Adyen numeric AVS result code (e.g. "7", "20", "0").
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $avsStatus = null;

        if (isset($additionalInfo['adyen_avs_result']) && empty($additionalInfo['adyen_avs_result']) == false) {
            $parts = explode(" ", $additionalInfo['adyen_avs_result']);
            $avsStatus = array_shift($parts);
        } elseif (isset($additionalInfo['additionalData']['avsResult']) &&
            empty($additionalInfo['additionalData']['avsResult']) == false) {
            $parts = explode(" ", $additionalInfo['additionalData']['avsResult']);
            $avsStatus = $parts[0];
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
