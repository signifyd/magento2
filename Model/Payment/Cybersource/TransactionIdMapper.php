<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\TransactionIdMapper as Base_TransactionIdMapper;

class TransactionIdMapper extends Base_TransactionIdMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['cybersource', 'chcybersource'];

    /**
     * Get transaction ID from database for Authorize.Net
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $apiResponse = $this->getSignifydPaymentData();

        if (isset($additionalInfo['transaction_id']) && empty($additionalInfo['transaction_id']) === false) {
            $transactionId = $additionalInfo['transaction_id'];
        } elseif (is_array($apiResponse) &&
            isset($apiResponse['transaction_id'])
        ) {
            $transactionId = $apiResponse['transaction_id'];
        }

        $message = 'Transaction ID found on payment mapper: ' . (empty($transactionId) ? 'false' : $transactionId);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($transactionId)) {
            $transactionId = parent::getPaymentData($order);
        }

        return $transactionId;
    }
}
