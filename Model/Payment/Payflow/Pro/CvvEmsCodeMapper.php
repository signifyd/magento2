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

        if (empty($additionalInfo['cvv2match']) == false && isset(self::$cvvMap[$additionalInfo['cvv2match']])) {
            $cvvStatus = self::$cvvMap[$additionalInfo['cvv2match']];

            if ($this->validate($cvvStatus) == false) {
                $cvvStatus = NULL;
            }
        }

        $this->logHelper->debug('CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus));

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($orderPayment);
        }

        return $cvvStatus;
    }
}
