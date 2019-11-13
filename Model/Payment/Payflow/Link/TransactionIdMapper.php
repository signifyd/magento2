<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\TransactionIdMapper as Base_TransactionIdMapper;

class TransactionIdMapper extends Base_TransactionIdMapper
{
    protected $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets transaction ID from Payflow response
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $transactionId = $this->getSignifydPaymentData('PNREF');

        $message = 'Transaction ID found on payment mapper: ' . (empty($transactionId) ? 'false' : $transactionId);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($transactionId)) {
            $transactionId = parent::getPaymentData($order);
        }

        return $transactionId;
    }
}
