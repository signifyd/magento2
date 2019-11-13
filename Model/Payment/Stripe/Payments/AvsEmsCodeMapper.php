<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    use MapperTrait;

    protected $allowedMethods = ['stripe_payments'];

    /**
     * List of mapping AVS codes
     *
     * Keys are concatenation of Street (address_line1_check) and postal code (address_postal_code_check) codes
     *
     * @var array
     */
    protected $avsMap = [
        'pass_pass' => 'Y',
        'pass_fail' => 'A',
        'fail_pass' => 'Z',
        'fail_fail' => 'N',
        'pass_unchecked' => 'B',
        'unchecked_pass' => 'P',
        'unchecked_fail' => 'N',
        'fail_unchecked' => 'N',
        'unchecked_unchecked' => 'U'
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
        $charge = $this->getCharge($order);

        if (is_object($charge) == false) {
            $avsStatus = null;
        }

        if (isset($charge->payment_method_details) &&
            isset($charge->payment_method_details->card) &&
            isset($charge->payment_method_details->card->checks) &&
            isset($charge->payment_method_details->card->checks->address_line1_check) &&
            isset($charge->payment_method_details->card->checks->address_postal_code_check)
        ) {
            $addressLine1Check = $charge->payment_method_details->card->checks->address_line1_check;
            $addressPostalCodeCheck = $charge->payment_method_details->card->checks->address_postal_code_check;

            if (isset($this->avsMap["{$addressLine1Check}_{$addressPostalCodeCheck}"])) {
                $avsStatus = $this->avsMap["{$addressLine1Check}_{$addressPostalCodeCheck}"];
            } else {
                $avsStatus = null;
            }
        } else {
            $avsStatus = null;
        }

        if ($this->validate($avsStatus) == false) {
            $avsStatus = null;
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
