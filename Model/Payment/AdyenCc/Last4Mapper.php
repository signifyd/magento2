<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Signifyd\Connect\Model\Payment\Base\Last4Mapper as Base_Last4Mapper;

class Last4Mapper extends Base_Last4Mapper
{
    protected $allowedMethods = ['adyen_cc'];

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return null|string
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        $additionalInfo = $order->getPayment()->getAdditionalInformation();

        if (isset($additionalInfo['cardSummary']) &&
            empty($additionalInfo['cardSummary']) === false &&
            strlen($additionalInfo['cardSummary']) === 4) {
            $last4 = $additionalInfo['cardSummary'];
        } elseif (isset($additionalInfo['additionalData']) &&
            isset($additionalInfo['additionalData']['cardSummary']) &&
            empty($additionalInfo['additionalData']['cardSummary']) === false &&
            strlen($additionalInfo['additionalData']['cardSummary']) === 4) {
            $last4 = $additionalInfo['additionalData']['cardSummary'];
        }

        $message = 'Last4 found on payment mapper: ' . (empty($last4) ? 'false' : 'true');
        $this->logger->debug($message, ['entity' => $order]);

        if (empty($last4)) {
            $last4 = parent::getPaymentData($order);
        }

        return $last4;
    }
}
