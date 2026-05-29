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
     * Returns the raw Authorize.net CIM AVS response code (M, N, P, S, U, B, etc.).
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
        }

        $message = 'AVS found on payment mapper: ' . (empty($avsStatus) ? 'false' : $avsStatus);
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($avsStatus)) {
            $avsStatus = parent::getPaymentData($order);
        }

        return $avsStatus;
    }
}
