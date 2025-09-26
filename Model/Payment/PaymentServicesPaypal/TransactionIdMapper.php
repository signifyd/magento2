<?php

namespace Signifyd\Connect\Model\Payment\PaymentServicesPaypal;

use Signifyd\Connect\Model\Payment\Base\TransactionIdMapper as Base_TransactionIdMapper;

class TransactionIdMapper extends Base_TransactionIdMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['payment_services_paypal_hosted_fields', 'payment_services_paypal_smart_buttons'];

    /**
     * Get transaction ID from database for Authorize.Net
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $transactionId = $order->getPayment()->getAdditionalInformation()['paypal_txn_id'];

        $message = 'Transaction id found on payment mapper: ' . (empty($transactionId) ? 'false' : $transactionId);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($transactionId)) {
            $transactionId = parent::getPaymentData($order);
        }

        return $transactionId;
    }
}
