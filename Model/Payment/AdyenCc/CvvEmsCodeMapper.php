<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = ['adyen_cc'];

    /**
     * List of mapping CVV codes
     *
     * @var array
     */
    private static $cvvMap = [
        '1' => 'M',
        '2' => 'N',
        '3' => 'P',
        '4' => 'S',
        '5' => 'U',
    ];

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (isset($additionalInfo['adyen_cvc_result']) && empty($additionalInfo['adyen_cvc_result']) === false) {
            $key = explode(" ", $additionalInfo['adyen_cvc_result']);
            $key = array_shift($key);

            if (isset(self::$cvvMap[$key]) && $this->validate(self::$cvvMap[$key])) {
                $cvvStatus = self::$cvvMap[$key];
            }
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
