<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    use MapperTrait;

    protected $allowedMethods = ['stripe_payments'];

    /**
     * Gets last 4 credit card digits
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);

        if (is_object($charge) == false) {
            $last4 = null;
        }

        if (isset($charge->payment_method_details) &&
            isset($charge->payment_method_details->card) &&
            isset($charge->payment_method_details->card->last4)
        ) {
            $last4 = $charge->payment_method_details->card->last4;
        } else {
            $last4 = null;
        }

        $message = 'Last4 found on payment mapper: ' . (empty($last4) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($last4)) {
            $last4 = parent::getPaymentData($order);
        }

        return $last4;
    }
}
