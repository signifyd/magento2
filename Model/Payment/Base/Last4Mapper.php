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
        $last4 = $last4 === null ? '' : $last4;

        $this->logger->debug('Last4 found on base mapper: ' . (empty($last4) ? 'false' : 'true'), ['entity' => $order]);

        return $last4;
    }

    /**
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        $last4 = $response->getLast4();

        return ((empty($last4) || strlen($last4) != 4) ? '' : $last4);
    }
}
