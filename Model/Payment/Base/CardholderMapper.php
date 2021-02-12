<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class CardholderMapper extends DataMapper
{
    /**
     * Gets cardholder name on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cardholder = $order->getPayment()->getCcOwner();
        $cardholder = $cardholder === null ? '' : $cardholder;

        $message = 'Card holder found on base mapper: ' . $cardholder;
        $this->logger->debug($message, ['entity' => $order]);

        return $cardholder;
    }

    /**
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        $cardholder = $response->getCardholder();

        $message = 'Card holder: ' . $cardholder;
        $this->logger->debug($message);

        return empty($cardholder) ? '' : $cardholder;
    }
}
