<?php

namespace Signifyd\Connect\Model\Payment\RootwaysAuthorizecimOption;

use Signifyd\Connect\Model\Payment\Base\BinMapper as Base_BinMapper;

class BinMapper extends Base_BinMapper
{
    /**
     * Gets last 4 credit card digits on Magento's default location on database
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
        }

        $message = 'Bin found on payment mapper: ' . (empty($bin) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($bin)) {
            $bin = parent::getPaymentData($order);
        }

        return $bin;
    }
}
