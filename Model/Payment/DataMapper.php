<?php

namespace Signifyd\Connect\Model\Payment;

use Magento\Framework\Registry;
use Signifyd\Connect\Api\PaymentVerificationInterface;
use Signifyd\Connect\Logger\Logger;

abstract class DataMapper implements PaymentVerificationInterface
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * List of payment methods codes
     * @var array
     */
    protected $allowedMethods = array();

    /**
     * Flag to prevent accidental loop for getCode/getData calls
     * @var bool
     */
    protected $getDataCalled = false;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Payflowlink constructor.
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry,
        Logger $logger
    )
    {
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * @param null $key
     * @return bool|mixed
     */
    public function getSignifydPaymentData($key = null)
    {
        $paymentData = $this->registry->registry('signifyd_payment_data');

        if (empty($key)) {
            return $paymentData;
        }

        if (is_array($paymentData)) {
            if (isset($paymentData[$key])) {
                return $paymentData[$key];
            } elseif (isset($paymentData[strtolower($key)])) {
                return $paymentData[strtolower($key)];
            } elseif (isset($paymentData[strtoupper($key)])) {
                return $paymentData[strtoupper($key)];
            }
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return bool
     */
    public function checkMethod(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        if (empty($this->allowedMethods) || in_array($orderPayment->getMethod(), $this->allowedMethods)) {
            return true;
        } else {
            throw new \InvalidArgumentException(
                'The "' . $orderPayment->getMethod() . '" it is not supported by ' . get_class($this) . ' mapper.'
            );
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    final public function getCode(\Magento\Sales\Model\Order $order)
    {
        if ($this->getDataCalled) {
            return null;
        } else {
            return $this->getData($order);
        }
    }

    /**
     * This method must be called to retrieve data. Use getPaymentMethod to actual retrieve data from payment method\
     * on extending classes
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    final public function getData(\Magento\Sales\Model\Order $order)
    {
        $this->checkMethod($order->getPayment());
        return $this->getPaymentData($order);
    }
}
