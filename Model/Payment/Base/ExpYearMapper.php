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
        $expYear = $expYear === null ? '' : $expYear;

        $message = 'Expiry year found on base mapper: ' . (empty($expYear) ? 'false' : $expYear);
        $this->logger->debug($message, ['entity' => $order]);

        return $expYear;
    }

    /**
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        $expYear = $response->getExpiryYear();

        return (empty($expYear) ? '' : $expYear);
    }
}
