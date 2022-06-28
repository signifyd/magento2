<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\BinMapper as Base_BinMapper;

class BinMapper extends Base_BinMapper
{
    protected $allowedMethods = ['adyen_cc'];

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (isset($additionalInfo['adyen_card_bin']) &&
            empty($additionalInfo['adyen_card_bin']) === false &&
            strlen($additionalInfo['adyen_card_bin']) === 6) {
            $bin = $additionalInfo['adyen_card_bin'];
        } elseif (isset($additionalInfo['additionalData']) &&
            isset($additionalInfo['additionalData']['cardBin']) &&
            empty($additionalInfo['additionalData']['cardBin']) === false &&
            strlen($additionalInfo['additionalData']['cardBin']) === 6) {
            $bin = $additionalInfo['additionalData']['cardBin'];
        }

        $message = 'Bin found on payment mapper: ' . (empty($bin) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($bin)) {
            $bin = parent::getPaymentData($order);
        }

        return $bin;
    }
}
