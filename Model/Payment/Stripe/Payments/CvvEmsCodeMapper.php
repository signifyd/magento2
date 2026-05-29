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
     * Returns the raw Stripe CVC check value (pass, fail, unchecked, unavailable).
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);
        $cvvStatus = null;

        if (is_object($charge) &&
            isset($charge->payment_method_details->card->checks->cvc_check)
        ) {
            $cvvCheck = $charge->payment_method_details->card->checks->cvc_check;
            $cvvStatus = empty($cvvCheck) ? null : $cvvCheck;
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
