<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Pro;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = array('payflowpro');

    /**
     * List of mapping AVS codes
     *
     * Keys are concatenation of Street (avsaddr) and ZIP (avszip) codes
     *
     * @var array
     */
    private static $avsMap = [
        'YN' => 'A',
        'NN' => 'N',
        'XX' => 'U',
        'YY' => 'Y',
        'NY' => 'Z'
    ];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $additionalInfo = $orderPayment->getAdditionalInformation();

        if (empty($additionalInfo['avsaddr']) ||
            empty($additionalInfo['avszip'])
        ) {
            return parent::getPaymentData($orderPayment);
        }

        $streetCode = $additionalInfo['avsaddr'];
        $zipCode = $additionalInfo['avszip'];
        $key = $streetCode . $zipCode;

        $avsCode = isset(self::$avsMap[$key]) ? self::$avsMap[$key] : 'U';

        if ($this->validate($avsCode)) {
            return $avsCode;
        } else {
            return parent::getPaymentData($orderPayment);
        }
    }
}
