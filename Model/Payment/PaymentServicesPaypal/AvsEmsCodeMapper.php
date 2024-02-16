<?php

namespace Signifyd\Connect\Model\Payment\PaymentServicesPaypal;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    public $allowedMethods = ['payment_services_paypal_hosted_fields', 'payment_services_paypal_smart_buttons'];

    /**
     * List of mapping AVS codes
     *
     * @var array
     */
    private static $avsMap = [
        'A' => 'A',
        'B' => 'B',
        'C' => 'I',
        'D' => 'D',
        'E' => 'S',
        'F' => 'M',
        'G' => 'G',
        'I' => 'G',
        'M' => 'Y',
        'N' => 'N',
        'P' => 'P',
        'R' => 'R',
        'S' => 'S',
        'U' => 'U',
        'W' => 'W',
        'X' => 'X',
        'Y' => 'Y',
        'Z' => 'Z'
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
        $avsStatus = $order->getPayment()->getCcAvsStatus();

        if (isset($avsStatus)) {
            if (in_array($avsStatus, array_keys(self::$avsMap))) {
                $avsStatus =  self::$avsMap[$avsStatus];
            } else {
                $avsStatus =  'E';
            }

            $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
            $this->logger->debug($message, ['entity' => $order]);
        } else {
            $avsStatus = parent::getPaymentData($order);
        }
        
        return $avsStatus;
    }
}
