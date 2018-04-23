<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class AvsEmsCodeMapper extends DataMapper
{
    /**
     * Default code
     *
     * @var string
     */
    protected $unavailableCode = 'U';

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
        return (empty($avsStatus) ? $this->unavailableCode : $avsStatus);
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
