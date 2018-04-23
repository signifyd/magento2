<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class BinMapper extends DataMapper
{
    /**
     * Gets last 4 credit card digits on Magento's default location on database
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        //Get CC number even if it is encrypted
        $ccNumber = $orderPayment->getData('cc_number');
        $ccNumber = preg_replace('/\D/', '', $ccNumber);
        if (empty($ccNumber) || strlen($ccNumber) < 6) {
            return '';
        }

        return substr($ccNumber, 0, 6);
    }
}
