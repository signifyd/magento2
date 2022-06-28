<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
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

        if (isset($expiryDate[0])) {
            $expMonth = $expiryDate[0];
        }

        $message = 'Expiry month found on payment mapper: ' . (empty($expMonth) ? 'false' : $expMonth);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expMonth)) {
            $expMonth = parent::getPaymentData($order);
        }

        return $expMonth;
    }
}
