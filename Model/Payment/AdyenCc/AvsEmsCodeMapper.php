<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = ['adyen_cc'];

    /**
     * List of mapping AVS codes
     *
     * @var array
     */
    private static $avsMap = [
        -1 => null,
        0 => null,
        1 => "A",
        2 => "N",
        3 => "U",
        4 => "S",
        5 => "U",
        6 => "Z",
        7 => "Y",
        8 => null,
        9 => "A",
        10 => "N",
        11 => null,
        12 => "A",
        13 => "N",
        14 => "Z",
        15 => "Z",
        16 => "N",
        17 => "N",
        18 => "U",
        19 => "Z",
        20 => "Y",
        21 => "A",
        22 => "N",
        23 => "Z",
        24 => "Y",
        25 => "A",
        26 => "N"
    ];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $key = null;

        if (isset($additionalInfo['adyen_avs_result']) && empty($additionalInfo['adyen_avs_result']) == false) {
            $key = explode(" ", $additionalInfo['adyen_avs_result']);
            $key = array_shift($key);
        } elseif (isset($additionalInfo['additionalData']) &&
            isset($additionalInfo['additionalData']['avsResult']) &&
            empty($additionalInfo['additionalData']['avsResult']) == false) {
            $keyArray = explode(" ", $additionalInfo['additionalData']['avsResult']);
            $key = $keyArray[0];
        }

        if (isset($key) && isset(self::$avsMap[$key]) && $this->validate(self::$avsMap[$key])) {
            $avsStatus = self::$avsMap[$key];
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
