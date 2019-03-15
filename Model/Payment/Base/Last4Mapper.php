<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class Last4Mapper extends DataMapper
{
    /**
     * Gets last 4 credit card digits on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $last4 = $order->getPayment()->getCcLast4();
        $last4 = is_null($last4) ? '' : $last4;

        $this->logger->debug('Last4 found on base mapper: ' . (empty($last4) ? 'false' : 'true'), array('entity' => $order));

        return $last4;
    }
}
