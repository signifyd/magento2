<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    use MapperTrait;

    /**
     * @var string[]
     */
    public $allowedMethods = ['stripe_payments'];

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool|mixed|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);

        if (is_object($charge) &&
            isset($charge->payment_method_details) &&
            isset($charge->payment_method_details->card) &&
            isset($charge->payment_method_details->card->checks) &&
            isset($charge->payment_method_details->card->checks->cvc_check)
        ) {
            $cvvStatus = $charge->payment_method_details->card->checks->cvc_check;
            $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'EMPTY' : $cvvStatus);
        } else {
            $cvvStatus = null;
            $message = 'CVV found on payment mapper: false';
        }

        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
