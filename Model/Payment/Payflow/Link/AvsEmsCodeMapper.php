<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = array('payflow_link', 'payflow_advanced');

    /**
     * Gets payment AVS verification code.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        $avsStatus = $this->getSignifydPaymentData('PROCAVS');

        if ($this->validate($avsStatus) == false) {
            $avsStatus = NULL;
        }

        $this->logHelper->debug('AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus));

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($orderPayment);
        }

        return $avsStatus;
    }
}
