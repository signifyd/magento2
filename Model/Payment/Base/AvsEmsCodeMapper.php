<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class AvsEmsCodeMapper extends DataMapper
{
    /**
     * Valid expected response codes
     *
     * @var array
     */
    protected $validAvsResponseCodes = [
        'X', 'Y', 'A', 'W', 'Z', 'N', 'U', 'R',
        'E', 'S', 'D', 'M', 'B', 'P', 'C', 'I', 'G'
    ];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $avsStatus = $order->getPayment()->getCcAvsStatus();
        $avsStatus = $this->validate($avsStatus) ? $avsStatus : null;

        $message = 'AVS found on base mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        return $avsStatus;
    }

    /**
     * Validate response
     *
     * @param $response
     * @return bool
     */
    public function validate($response)
    {
        return in_array($response, $this->validAvsResponseCodes);
    }

    /**
     * @param \Signifyd\Models\Payment\Response\ResponseInterface $response
     * @return string
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response)
    {
        $avsStatus = $response->getAvs();

        $message = 'Response AVS: ' . ($response->getAvs());
        $this->logger->debug($message);

        return ($this->validate($avsStatus) ? $avsStatus : null);
    }
}
