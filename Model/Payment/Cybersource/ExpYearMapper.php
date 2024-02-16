<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\ExpYearMapper as Base_ExpYearMapper;

class ExpYearMapper extends Base_ExpYearMapper
{
    public $allowedMethods = ['cybersource', 'chcybersource'];

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $apiResponse = $this->getSignifydPaymentData();
        $expiryDate = [];

        if (isset($additionalInfo['card_expiry_date']) && empty($additionalInfo['card_expiry_date']) == false) {
            $expiryDate = explode("-", $additionalInfo['card_expiry_date']);
        } elseif (is_array($apiResponse) &&
            isset($apiResponse['req_card_expiry_date'])
        ) {
            $expiryDate = explode("-", $apiResponse['req_card_expiry_date']);
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
