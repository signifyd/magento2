<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Pro;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = ['payflowpro'];

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
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (empty($additionalInfo['avsaddr']) == false &&
            empty($additionalInfo['avszip']) == false
        ) {
            $streetCode = $additionalInfo['avsaddr'];
            $zipCode = $additionalInfo['avszip'];
            $key = $streetCode . $zipCode;

            if (isset(self::$avsMap[$key]) && $this->validate(self::$avsMap[$key])) {
                $avsStatus = self::$avsMap[$key];
            }
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
