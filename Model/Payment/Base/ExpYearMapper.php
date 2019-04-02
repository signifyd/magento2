<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class ExpYearMapper extends DataMapper
{
    /**
     * Gets credit card expiration year on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $expYear = $order->getPayment()->getCcExpYear();
        $expYear = is_null($expYear) ? '' : $expYear;

        $this->logger->debug('Expiry year found on base mapper: ' . (empty($expYear) ? 'false' : $expYear), array('entity' => $order));

        return $expYear;
    }
}
