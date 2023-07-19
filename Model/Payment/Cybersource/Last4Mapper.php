<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    protected $allowedMethods = ['cybersource', 'chcybersource'];

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $apiResponse = $this->getSignifydPaymentData();

        if (isset($additionalInfo['card_number']) &&
            empty($additionalInfo['card_number']) === false &&
            strlen($additionalInfo['card_number']) === 4) {
            $last4 = $additionalInfo['card_number'];
        } elseif (is_array($apiResponse) &&
            isset($apiResponse['req_card_number'])
        ) {
            $last4 = substr($apiResponse['req_card_number'], -4);
        }

        $message = 'Last4 found on payment mapper: ' . (empty($last4) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($last4)) {
            $last4 = parent::getPaymentData($order);
        }

        return $last4;
    }
}
