<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    protected $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets last 4 credit card digits from Payflow response
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $last4 = $this->getSignifydPaymentData('ACCT');
        $last4 = substr($last4, -4);

        $message = 'Last4 found on payment mapper: ' . (empty($last4) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($last4)) {
            $last4 = parent::getPaymentData($order);
        }

        return $last4;
    }
}
