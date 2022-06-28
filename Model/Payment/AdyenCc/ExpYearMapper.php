<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\ExpYearMapper as Base_ExpYearMapper;

class ExpYearMapper extends Base_ExpYearMapper
{
    protected $allowedMethods = ['adyen_cc'];

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $expiryDate = [];

        if (isset($additionalInfo['adyen_expiry_date']) && empty($additionalInfo['adyen_expiry_date']) == false) {
            $expiryDate = explode("/", $additionalInfo['adyen_expiry_date']);
        } elseif (isset($additionalInfo['additionalData']) &&
            isset($additionalInfo['additionalData']['expiryDate']) &&
            empty($additionalInfo['additionalData']['expiryDate']) == false) {
            $expiryDate = explode("/", $additionalInfo['additionalData']['expiryDate']);
        }

        if (isset($expiryDate[1])) {
            $expYear = $expiryDate[1];
        }

        $message = 'Expiry year found on payment mapper: ' . (empty($expYear) ? 'false' : $expYear);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expYear)) {
            $expYear = parent::getPaymentData($order);
        }

        return $expYear;
    }
}
