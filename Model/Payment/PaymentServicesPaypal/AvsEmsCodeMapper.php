<?php

namespace Signifyd\Connect\Model\Payment\PaymentServicesPaypal;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['payment_services_paypal_hosted_fields', 'payment_services_paypal_smart_buttons'];

    /**
     * Gets payment AVS verification code.
     *
     * Returns the raw PayPal AVS response code (A, B, C, D, E, F, G, I, M, N, P, R, S, U, W, X, Y, Z).
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $avsStatus = $order->getPayment()->getCcAvsStatus();

        if (!empty($avsStatus)) {
            $message = 'AVS found on payment mapper: ' . $avsStatus;
            $this->logger->debug($message, ['entity' => $order]);
            return $avsStatus;
        }

        return parent::getPaymentData($order);
    }
}
