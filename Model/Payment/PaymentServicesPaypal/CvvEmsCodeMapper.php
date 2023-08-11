<?php

namespace Signifyd\Connect\Model\Payment\PaymentServicesPaypal;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    protected $allowedMethods = ['payment_services_paypal_hosted_fields', 'payment_services_paypal_smart_buttons'];

    /**
     * List of mapping CVV codes
     *
     * @var array
     */
    private static $cvvMap = [
        'E' => 'E',
        'I' => 'I',
        'M' => 'M',
        'N' => 'N',
        'P' => 'P',
        'S' => 'N',
        'U' => 'U',
        'X' => 'X'
    ];

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cvvStatus = $order->getPayment()->getCcCidStatus();

        if (isset($cvvStatus) ) {
            if (in_array($cvvStatus, array_keys(self::$cvvMap))) {
                $cvvStatus =  self::$cvvMap[$cvvStatus];
            } else {
                $cvvStatus = null;
            }

            $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
            $this->logger->debug($message, ['entity' => $order]);
        } else {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}