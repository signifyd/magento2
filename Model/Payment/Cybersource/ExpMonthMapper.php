<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
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

        if (isset($additionalInfo['card_expiry_date'])) {
            $expiryDate = explode("-", $additionalInfo['card_expiry_date']);
        } elseif (is_array($apiResponse) &&
            isset($apiResponse['req_card_expiry_date'])
        ) {
            $expiryDate = explode("-", $apiResponse['req_card_expiry_date']);
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
