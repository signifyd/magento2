<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\ExpMonthMapper as Base_ExpMonthMapper;

class ExpMonthMapper extends Base_ExpMonthMapper
{
    protected $allowedMethods = array('payflow_link', 'payflow_advanced');

    /**
     * Gets expiry month from Payflow response
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $expMonth = $this->getSignifydPaymentData('EXPDATE');
        $expMonth = substr($expMonth, 0, 2);

        $this->logHelper->debug('Expiry month found on payment mapper: ' . (empty($expMonth) ? 'false' : $expMonth));

        if (empty($expMonth)) {
            $expMonth = parent::getPaymentData($orderPayment);
        }

        return $expMonth;
    }
}
