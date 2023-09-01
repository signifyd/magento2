<?php

namespace Signifyd\Connect\Model\Payment\Authorizenet;

use Signifyd\Connect\Model\Payment\Base\TransactionIdMapper as Base_TransactionIdMapper;

class TransactionIdMapper extends Base_TransactionIdMapper
{
    protected $allowedMethods = ['authorizenet_directpost'];

    /**
     * Get transaction ID from database for Authorize.Net
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $transactionId = parent::getPaymentData($order);

        if (empty($transactionId)) {
            $transactionId = null;
        } else {
            $transactionId = str_replace('-capture', '', $transactionId);
        }

        return $transactionId;
    }
}
