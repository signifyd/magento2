<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class CvvEmsCodeMapper extends DataMapper
{
    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cidStatus = $order->getPayment()->getCcCidStatus();
        $cidStatus = empty($cidStatus) ? null : $cidStatus;

        $message = 'CVV found on base mapper: ' . (empty($cidStatus) ? 'false' : $cidStatus);
        $this->logger->debug($message, ['entity' => $order]);

        return $cidStatus;
    }

    /**
     * Get payment data from gateway response method.
     *
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string|null
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        $cvvStatus = $response->getCvv();
        return empty($cvvStatus) ? null : $cvvStatus;
    }
}
