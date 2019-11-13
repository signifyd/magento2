<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\ExpYearMapper as Base_ExpYearMapper;

class ExpYearMapper extends Base_ExpYearMapper
{
    use MapperTrait;

    protected $allowedMethods = ['stripe_payments'];

    /**
     * Gets expiry year
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);

        if (is_object($charge) == false) {
            $expYear = null;
        }

        if (isset($charge->payment_method_details) &&
            isset($charge->payment_method_details->card) &&
            isset($charge->payment_method_details->card->exp_year)
        ) {
            $expYear = $charge->payment_method_details->card->exp_year;
            $expYear = substr($expYear, -2);
        } else {
            $expYear = null;
        }

        $message = 'Expiry year found on payment mapper: ' . (empty($expYear) ? 'false' : $expYear);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expYear)) {
            $expYear = parent::getPaymentData($order);
        }

        return $expYear;
    }
}
