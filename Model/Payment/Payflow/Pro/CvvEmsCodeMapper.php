<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Pro;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = array('payflowpro');

    /**
     * List of mapping CVV codes
     *
     * @var array
     */
    private static $cvvMap = [
        'Y' => 'M',
        'N' => 'N',
        'X' => 'U'
    ];

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $additionalInfo = $orderPayment->getAdditionalInformation();

        if (empty($additionalInfo['cvv2match'])) {
            return parent::getPaymentData($orderPayment);
        }

        $cvv = $additionalInfo['cvv2match'];

        if (isset(self::$cvvMap[$cvv]) && $this->validate(self::$cvvMap[$cvv])) {
            return self::$cvvMap[$cvv];
        } else {
            return parent::getPaymentData($orderPayment);
        }
    }
}
