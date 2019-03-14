<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class ExpYearMapper extends DataMapper
{
    /**
     * Gets credit card expiration year on Magento's default location on database
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $expYear = $orderPayment->getCcExpYear();
        $expYear = is_null($expYear) ? '' : $expYear;

        $this->logger->debug('Expiry year found on base mapper: ' . (empty($expYear) ? 'false' : $expYear));

        return $expYear;
    }
}
