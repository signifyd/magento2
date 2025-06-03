<?php

namespace Signifyd\Connect\Api;

interface PaymentMethodMappingInterface
{
    /**
     * Must return desired data. This is the event that is called from outside
     *
     * Throws an exception if provided payment method is different to verification implementation.
     *
     * @param \Magento\Sales\Model\Order|\Magento\Quote\Model\Quote $entity
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getData($entity);

    /**
     * Get payment method from order method.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getPaymentMethodFromOrder(\Magento\Sales\Model\Order $order);

    /**
     * Get payment method from quote method.
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getPaymentMethodFromQuote(\Magento\Quote\Model\Quote $quote);
}
