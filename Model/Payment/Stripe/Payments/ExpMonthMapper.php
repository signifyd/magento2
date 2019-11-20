<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
{
    use MapperTrait;

    protected $allowedMethods = ['stripe_payments'];

    /**
     * Gets expiry month
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);

        if (is_object($charge) == false) {
            $expMonth = null;
        }

        if (isset($charge->payment_method_details) &&
            isset($charge->payment_method_details->card) &&
            isset($charge->payment_method_details->card->exp_month)
        ) {
            $expMonth = $charge->payment_method_details->card->exp_month;
        } else {
            $expMonth = null;
        }

        $message = 'Expiry month found on payment mapper: ' . (empty($expMonth) ? 'false' : $expMonth);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expMonth)) {
            $expMonth = parent::getPaymentData($order);
        }

        return $expMonth;
    }
}
