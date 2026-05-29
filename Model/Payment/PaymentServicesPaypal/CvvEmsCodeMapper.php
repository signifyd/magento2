<?php

namespace Signifyd\Connect\Model\Payment\PaymentServicesPaypal;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['payment_services_paypal_hosted_fields', 'payment_services_paypal_smart_buttons'];

    /**
     * Gets payment CVV verification code.
     *
     * Returns the raw PayPal CVV response code (E, I, M, N, P, S, U, X).
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $cvvStatus = $order->getPayment()->getCcCidStatus();

        if (!empty($cvvStatus)) {
            $message = 'CVV found on payment mapper: ' . $cvvStatus;
            $this->logger->debug($message, ['entity' => $order]);
            return $cvvStatus;
        }

        return parent::getPaymentData($order);
    }
}
