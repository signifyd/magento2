<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    protected $allowedMethods = array('payflow_link', 'payflow_advanced');

    /**
     * Gets last 4 credit card digits from Payflow response
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $last4 = $this->getSignifydPaymentData('ACCT');
        $last4 = substr($last4, -4);
        return (empty($last4) ? parent::getPaymentData($orderPayment) : $last4);
    }
}
