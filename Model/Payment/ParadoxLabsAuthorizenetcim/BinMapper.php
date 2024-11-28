<?php

namespace Signifyd\Connect\Model\Payment\ParadoxLabsAuthorizenetcim;

use Signifyd\Connect\Model\Payment\Base\BinMapper as Base_BinMapper;

class BinMapper extends Base_BinMapper
{
    /**
     * Gets bin digits on Magento's payment additional information
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        $bin = null;

        if (isset($additionalInfo['card_bin'])) {
            $bin = $additionalInfo['card_bin'];
        } elseif (isset($additionalInfo['cc_bin'])) {
            $bin = $additionalInfo['cc_bin'];
        }

        $message = 'Bin found on payment mapper: ' . (empty($bin) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($bin)) {
            $bin = parent::getPaymentData($order);
        }

        return $bin;
    }
}
