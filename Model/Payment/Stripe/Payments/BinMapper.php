<?php

namespace Signifyd\Connect\Model\Payment\Stripe\Payments;

use Signifyd\Connect\Model\Payment\Base\BinMapper as Base_BinMapper;

class BinMapper extends Base_BinMapper
{
    use MapperTrait;

    /**
     * @var string[]
     */
    public $allowedMethods = ['stripe_payments'];

    /**
     * Gets credit card bin
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $charge = $this->getCharge($order);
        $bin = null;

        if (isset($charge->payment_method_details->card->iin)) {
            $bin = $charge->payment_method_details->card->iin;
        }

        $this->logger->debug('Bin found on payment mapper: ' . (empty($bin) ? 'false' : 'true'), ['entity' => $order]);

        return empty($bin) ? parent::getPaymentData($order) : $bin;
    }
}
