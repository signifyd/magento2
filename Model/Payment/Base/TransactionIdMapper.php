<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class TransactionIdMapper extends DataMapper
{
    /**
     * Transaction ID should always came from Magento database, so go directly for it
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getData(\Magento\Sales\Model\Order $order)
    {
        $this->checkMethod($order->getPayment());
        return $this->getPaymentData($order);
    }

    /**
     * Gets transaction ID on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        return $this->getTransactionId($order);
    }

    /**
     * Transaction ID should came from Magento database
     *
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        return false;
    }
}
