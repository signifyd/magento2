<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    use MapperTrait;

    /**
     * @var string[]
     */
    public $allowedMethods = ['stripe_payments'];

    /**
     * Gets payment AVS verification code.
     *
     * Stripe provides two separate AVS components. Returns an array with
     * 'addressMatchCode' (street) and 'zipMatchCode' (postal) for the API v3
     * verifications.avsResponse object.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);

        if (is_object($charge) &&
            isset($charge->payment_method_details->card->checks->address_line1_check) &&
            isset($charge->payment_method_details->card->checks->address_postal_code_check)
        ) {
            $addressLine1Check = $charge->payment_method_details->card->checks->address_line1_check;
            $addressPostalCodeCheck = $charge->payment_method_details->card->checks->address_postal_code_check;

            if (!empty($addressLine1Check) && !empty($addressPostalCodeCheck)) {
                $avsStatus = [
                    'addressMatchCode' => $addressLine1Check,
                    'zipMatchCode'     => $addressPostalCodeCheck
                ];

                $message = 'AVS found on payment mapper: ' .
                    $avsStatus['addressMatchCode'] . '/' . $avsStatus['zipMatchCode'];
                $this->logger->debug($message, ['entity' => $order]);

                return $avsStatus;
            }
        }

        $this->logger->debug('AVS found on payment mapper: false', ['entity' => $order]);
        return parent::getPaymentData($order);
    }
}
