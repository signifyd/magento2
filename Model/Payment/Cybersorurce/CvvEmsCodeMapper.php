<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = ['cybersource'];

    /**
     * List of mapping CVV codes
     *
     * @var array
     */
    private static $cvvMap = [
        "D" => "U",
        "I" => "N",
        "X" => "U",
        "1" => "U",
        "2" => "N",
        "3" => "P"
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

        if (isset($additionalInfo['auth_cv_result']) &&
            isset(self::$avsMap[$additionalInfo['auth_cv_result']]) &&
            $this->validate(self::$avsMap[$additionalInfo['auth_cv_result']])) {
            $cvvStatus = self::$avsMap[$additionalInfo['auth_cv_result']];
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
