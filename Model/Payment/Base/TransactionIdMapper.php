<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class TransactionIdMapper extends DataMapper
{
    /**
     * Gets transaction ID on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $transactionId = $order->getPayment()->getCcTransId();

        if (empty($transactionId)) {
            $transactionId = $order->getPayment()->getLastTransId();
        }

        return $transactionId;
    }
}
