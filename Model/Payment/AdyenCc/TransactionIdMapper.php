<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\TransactionIdMapper as Base_TransactionIdMapper;

class TransactionIdMapper extends Base_TransactionIdMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['adyen_cc','adyen_pay_by_link'];

    /**
     * Get transaction ID from database for Authorize.Net
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (isset($additionalInfo['pspReference']) && empty($additionalInfo['pspReference']) === false) {
            $transactionId = $additionalInfo['pspReference'];
        }

        $message = 'Transaction ID found on payment mapper: ' . (empty($transactionId) ? 'false' : $transactionId);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($transactionId)) {
            $transactionId = parent::getPaymentData($order);
        }

        return $transactionId;
    }
}
