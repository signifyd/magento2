<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PaymentMethods implements OptionSourceInterface
{
    protected $paymentHelper;

    public function __construct(\Magento\Payment\Helper\Data $paymentHelper)
    {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Returns array to be used in multiselect on back-end
     *
     * @return array
     */
    public function toOptionArray()
    {
        // get available payment methods
        $arr = [];
        foreach ($this->paymentHelper->getPaymentMethodList() as $code => $title) {
            if (!empty($code) && !empty($title)) {
                $arr[] = ['value' => $code, 'label' => __($title) . " [$code]"];
            }
        }
        return $arr;
    }
}
