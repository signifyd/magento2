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

    /**
     * Fetch data directly from payment gateway response instead of getting it from Magento database
     * A class implementing GatewayInterface should be implemented and setup on settings in order to
     * retrieve a ResponseInterface that will be passed to this method
     *
     * @param \Signifyd\Models\Payment\GatewayInterface $paymentGateway
     * @return mixed
     */
    public function getPaymentDataFromGatewayResponse(\Signifyd\Models\Payment\Response\ResponseInterface $response);
}
