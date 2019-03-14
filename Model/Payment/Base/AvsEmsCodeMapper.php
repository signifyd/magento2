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
    protected $validAvsResponseCodes = array('X', 'Y', 'A', 'W', 'Z', 'N', 'U', 'R', 'E', 'S', 'D', 'M', 'B', 'P', 'C', 'I', 'G');

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $avsStatus = $orderPayment->getCcAvsStatus();
        $avsStatus = $this->validate($avsStatus) ? $avsStatus : NULL;

        $this->logger->debug('AVS found on base mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus));

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
}
