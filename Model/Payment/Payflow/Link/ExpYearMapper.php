<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\ExpYearMapper as Base_ExpYearMapper;

class ExpYearMapper extends Base_ExpYearMapper
{
    protected $allowedMethods = array('payflow_link', 'payflow_advanced');

    /**
     * Gets expiry year from Payflow response
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $expYear = $this->getSignifydPaymentData('EXPDATE');
        $expYear = substr($expYear, -2);

        $this->logHelper->debug('Expiry year found on payment mapper: ' . (empty($expYear) ? 'false' : $expYear));

        if (empty($expYear)) {
            $expYear = parent::getPaymentData($orderPayment);
        }

        return $expYear;
    }
}
