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
     * @param OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment);

    /**
     * Actually gets data from payment method
     * Throws an exception if provided payment method is different to verification implementation.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment);

    /**
     * Compatibility with Magento built in PaymentVerificationInterface
     *
     * @param OrderPaymentInterface $orderPayment
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getCode(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment);
}
