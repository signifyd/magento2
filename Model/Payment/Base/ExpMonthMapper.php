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
        $expMonth = is_null($expMonth) ? '' : $expMonth;

        $this->logger->debug('Expiry month found on base mapper: ' . (empty($expMonth) ? 'false' : $expMonth), ['entity' => $order]);

        return $expMonth;
    }
}
