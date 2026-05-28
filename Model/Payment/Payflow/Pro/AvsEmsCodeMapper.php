<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Pro;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['payflowpro'];

    /**
     * Gets payment AVS verification code.
     *
     * Payflow Pro provides two separate AVS components. Returns an array with
     * 'addressMatchCode' (street) and 'zipMatchCode' (postal) for the API v3
     * verifications.avsResponse object.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (empty($additionalInfo['avsaddr']) == false &&
            empty($additionalInfo['avszip']) == false
        ) {
            $avsStatus = [
                'addressMatchCode' => $additionalInfo['avsaddr'],
                'zipMatchCode'     => $additionalInfo['avszip']
            ];

            $message = 'AVS found on payment mapper: ' .
                $avsStatus['addressMatchCode'] . '/' . $avsStatus['zipMatchCode'];
            $this->logger->debug($message, ['entity' => $order]);

            return $avsStatus;
        }

        $this->logger->debug('AVS found on payment mapper: false', ['entity' => $order]);
        return parent::getPaymentData($order);
    }
}
