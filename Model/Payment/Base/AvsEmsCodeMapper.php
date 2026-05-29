<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class AvsEmsCodeMapper extends DataMapper
{
    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $avsStatus = $order->getPayment()->getCcAvsStatus();
        $avsStatus = empty($avsStatus) ? null : $avsStatus;

        $message = 'AVS found on base mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        return $avsStatus;
    }

    /**
     * Get payment data from gateway response method.
     *
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string|null
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        $avsStatus = $response->getAvs();

        $message = 'Response AVS: ' . $avsStatus;
        $this->logger->debug($message);

        return empty($avsStatus) ? null : $avsStatus;
    }
}
