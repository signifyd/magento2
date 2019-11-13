<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\ExpYearMapper as Base_ExpYearMapper;

class ExpYearMapper extends Base_ExpYearMapper
{
    protected $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets expiry year from Payflow response
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $expYear = $this->getSignifydPaymentData('EXPDATE');
        $expYear = substr($expYear, -2);

        $message = 'Expiry year found on payment mapper: ' . (empty($expYear) ? 'false' : $expYear);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($expYear)) {
            $expYear = parent::getPaymentData($order);
        }

        return $expYear;
    }
}
