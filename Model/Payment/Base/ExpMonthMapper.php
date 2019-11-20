<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class ExpMonthMapper extends DataMapper
{
    /**
     * Gets credit card expiration month on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $expMonth = $order->getPayment()->getCcExpMonth();
        $expMonth = $expMonth === null ? '' : $expMonth;

        $message = 'Expiry month found on base mapper: ' . (empty($expMonth) ? 'false' : $expMonth);
        $this->logger->debug($message, ['entity' => $order]);

        return $expMonth;
    }
}
