<?php

namespace Signifyd\Connect\Model\Api\CaseData\Transactions\PaymentMethod;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class Mapper extends Base
{
    /**
     * @param Order $order
     * @return int|string
     */
    public function getPaymentMethodFromOrder(Order $order)
    {
        $this->logger->info("Post auth mapping for payment method " . $order->getPayment()->getMethod());

        return $this->makePaymentMethod($order->getPayment()->getMethod());
    }

    /**
     * @param Quote $quote
     * @return int|string
     */
    public function getPaymentMethodFromQuote(Quote $quote)
    {
        $this->logger->info("Pre auth mapping for payment method " . $quote->getPayment()->getMethod());

        return $this->makePaymentMethod($quote->getPayment()->getMethod());
    }
}
