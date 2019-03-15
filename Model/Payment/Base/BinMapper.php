<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Signifyd\Connect\Model\Payment\DataMapper;

class BinMapper extends DataMapper
{
    /**
     * Gets last 4 credit card digits on Magento's default location on database
     *
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        //Get CC number even if it is encrypted
        $ccNumber = $order->getPayment()->getData('cc_number');
        $ccNumber = preg_replace('/\D/', '', $ccNumber);

        if (empty($ccNumber) || strlen($ccNumber) < 6) {
            $bin = '';
        } else {
            $bin = substr($ccNumber, 0, 6);
        }

        $this->logger->debug('Bin found on base mapper: ' . (empty($bin) ? 'false' : $bin), array('entity' => $order));

        return $bin;
    }
}
