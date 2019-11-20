<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
{
    protected $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets expiry month from Payflow response
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $expMonth = $this->getSignifydPaymentData('EXPDATE');
        $expMonth = substr($expMonth, 0, 2);

        $message = 'Expiry month found on payment mapper: ' . (empty($expMonth) ? 'false' : $expMonth);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expMonth)) {
            $expMonth = parent::getPaymentData($order);
        }

        return $expMonth;
    }
}
