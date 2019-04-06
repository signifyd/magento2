<?php

namespace Signifyd\Connect\Api;

/**
 * Payment provider data interface.
 *
 * Custom payment methods might implement this interface to provide
 * specific mapping for payment methods, cardholder name and bin.
 * The payment methods can map payment method info from internal sources,
 * like additional information, to specific international codes.
 *
 * There are no default implementation of this interface, because data locations
 * depends on payment method integration specifics.
 */
interface PaymentVerificationInterface
{
    /**
     * Must return desired data. This is the event that is called from outside
     * Throws an exception if provided payment method is different to verification implementation.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getData(\Magento\Sales\Model\Order $order);

    /**
     * Actually gets data from payment method
     * Throws an exception if provided payment method is different to verification implementation.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getPaymentData(\Magento\Sales\Model\Order $order);

    /**
     * Compatibility with Magento built in PaymentVerificationInterface
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getCode(\Magento\Sales\Model\Order $order);
}
