<?php

namespace Signifyd\Connect\Model\Payment\Cybersource;

use Signifyd\Connect\Model\Payment\Base\BinMapper as Base_BinMapper;

class BinMapper extends Base_BinMapper
{
    /**
     * @var string[]
     */
    public $allowedMethods = ['cybersource', 'chcybersource'];

    /**
     * Get payment data method.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $apiResponse = $this->getSignifydPaymentData();
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (isset($additionalInfo['card_bin']) &&
            empty($additionalInfo['card_bin']) === false &&
            strlen($additionalInfo['card_bin']) === 6) {
            $bin = $additionalInfo['card_bin'];
        } elseif (is_array($apiResponse) &&
            isset($apiResponse['afsReply']) &&
            isset($apiResponse['afsReply']->cardBin)
        ) {
            $bin = $apiResponse['afsReply']->cardBin;
        }

        $message = 'Bin found on payment mapper: ' . (empty($bin) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($bin)) {
            $bin = parent::getPaymentData($order);
        }

        return $bin;
    }
}
