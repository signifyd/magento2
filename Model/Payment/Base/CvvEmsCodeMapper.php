<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class CvvEmsCodeMapper extends DataMapper
{
    /**
     * Default code
     *
     * @var string
     */
    protected $notProvidedCode = 'P';

    /**
     * Valid expected CVV codes
     *
     * @var array
     */
    protected $validCvvResponseCodes = array('M', 'N', 'P', 'S', 'U');

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $cidStatus = $orderPayment->getCcCidStatus();
        return (empty($cidStatus) ? $this->notProvidedCode : $cidStatus);
    }

    public function validate($response)
    {
        return in_array($response, $this->validCvvResponseCodes);
    }
}
