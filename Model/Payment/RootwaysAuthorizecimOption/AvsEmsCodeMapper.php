<?php

namespace Signifyd\Connect\Model\Payment\RootwaysAuthorizecimOption;

use Signifyd\Connect\Model\Payment\Base\AvsEmsCodeMapper as Base_AvsEmsCodeMapper;

class AvsEmsCodeMapper extends Base_AvsEmsCodeMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['rootways_authorizecim_option'];

    /**
     * Gets payment AVS verification code.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $avsStatus = null;

        if (empty($additionalInfo['avs_response_code']) == false) {
            $avsStatus = $additionalInfo['avs_response_code'];
            if ($avsStatus == 'B') {
                $avsStatus = 'U';
            }
        }

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
