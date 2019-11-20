<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\CvvEmsCodeMapper as Base_CvvEmsCodeMapper;

class CvvEmsCodeMapper extends Base_CvvEmsCodeMapper
{
    use MapperTrait;

    protected $allowedMethods = ['stripe_payments'];

    /**
     * List of mapping CVV codes
     *
     * Keys are concatenation cvc_check field from Stripe charge object
     *
     * @var array
     */
    protected $cvvMap = [
        'pass' => 'M',
        'fail' => 'N',
        'unchecked' => 'P'
    ];

    /**
     * Gets payment CVV verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool|mixed|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);

        if (is_object($charge) == false) {
            $cvvStatus = null;
        }

        if (isset($charge->payment_method_details) &&
            isset($charge->payment_method_details->card) &&
            isset($charge->payment_method_details->card->checks) &&
            isset($charge->payment_method_details->card->checks->cvc_check)
        ) {
            $cvvCheck = $charge->payment_method_details->card->checks->cvc_check;

            if (isset($this->cvvMap[$cvvCheck])) {
                $cvvStatus = $this->cvvMap[$cvvCheck];
            } else {
                $cvvStatus = null;
            }
        } else {
            $cvvStatus = null;
        }

        $message = 'CVV found on payment mapper: ' . (empty($cvvStatus) ? 'false' : $cvvStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($cvvStatus)) {
            $cvvStatus = parent::getPaymentData($order);
        }

        return $cvvStatus;
    }
}
