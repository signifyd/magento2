<?php

namespace Signifyd\Connect\Api;

interface PaymentMethodMappingInterface
{
    /**
     * Must return desired data. This is the event that is called from outside
     * Throws an exception if provided payment method is different to verification implementation.
     *
     * @param \Magento\Sales\Model\Order|\Magento\Quote\Model\Quote $order
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getData($entity);

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getPaymentMethodFromOrder(\Magento\Sales\Model\Order $order);


    /**     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function getPaymentMethodFromQuote(\Magento\Quote\Model\Quote $quote);
}
