<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class PaymentMethodMapper extends PaymentMethodBase
{
    /**
     * @param Order $order
     * @return int|string
     */
    public function getPaymentMethodFromOrder(Order $order)
    {
        $paymentMethod = $this->makePaymentMethod($order->getPayment()->getMethod());

        $message = 'Payment method found on base mapper: ' . (empty($paymentMethod) ? 'false' : $paymentMethod);
        $this->logger->debug($message, ['entity' => $order]);

        return $paymentMethod;
    }

    /**
     * @param Quote $quote
     * @return int|string
     */
    public function getPaymentMethodFromQuote(Quote $quote)
    {
        $paymentMethod = $this->makePaymentMethod($quote->getPayment()->getMethod());

        $message = 'Payment method found on base mapper: ' . (empty($paymentMethod) ? 'false' : $paymentMethod);
        $this->logger->debug($message, ['entity' => $quote]);

        return $paymentMethod;
    }
}
