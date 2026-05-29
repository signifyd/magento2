<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['cybersource', 'chcybersource'];

    /**
     * Gets payment CVV verification code.
     *
     * Returns the raw CyberSource CVV result code (M, N, P, U, D, I, X, 1, 2, 3).
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $cvvStatus = null;

        if (isset($additionalInfo['auth_cv_result']) && $additionalInfo['auth_cv_result'] !== '') {
            $cvvStatus = $additionalInfo['auth_cv_result'];
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
