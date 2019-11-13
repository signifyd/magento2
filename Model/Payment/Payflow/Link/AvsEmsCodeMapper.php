<?php

namespace Signifyd\Connect\Model\Payment\Payflow\Link;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    protected $allowedMethods = ['payflow_link', 'payflow_advanced'];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException If specified order payment has different payment method code.
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $avsStatus = $this->getSignifydPaymentData('PROCAVS');

        if ($this->validate($avsStatus) == false) {
            $avsStatus = null;
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
