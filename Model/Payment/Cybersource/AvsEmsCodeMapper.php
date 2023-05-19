<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = ['cybersource', 'chcybersource'];

    /**
     * List of mapping AVS codes
     *
     * @var array
     */
    private static $avsMap = [
        "F" => "Z",
        "H" => "Y",
        "T" => "A",
        "1" => "S",
        "2" => "E",
        "K" => "N",
        "L" => "Z",
        "O" => "A"
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
        $apiResponse = $this->getSignifydPaymentData();

        if (isset($additionalInfo['auth_avs_code']) &&
            isset(self::$avsMap[$additionalInfo['auth_avs_code']]) &&
            $this->validate(self::$avsMap[$additionalInfo['auth_avs_code']])) {
            $avsStatus = self::$avsMap[$additionalInfo['auth_avs_code']];
        } elseif (is_array($apiResponse) &&
            isset($apiResponse['ccAuthReply']) &&
            isset($apiResponse['ccAuthReply']->avsCode)
        ) {
            $avsStatus = $apiResponse['ccAuthReply']->avsCode;
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
